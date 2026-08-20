import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;
import com.sun.net.httpserver.HttpServer;

import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;
import java.util.concurrent.Executors;
import java.util.concurrent.TimeUnit;

/**
 * Wrapper HTTP minimal autour de Mustang-CLI (org.mustangproject:Mustang-CLI, Apache-2.0),
 * seul point d'entree du conteneur "mustang" (ADR-008, docker-compose.yml) : jamais expose
 * publiquement, appele uniquement par les services "backend"/"worker" sur le reseau Docker
 * interne.
 *
 * Aucune bibliotheque tierce : le corps de requete est le contenu binaire brut du document
 * (pas de multipart/form-data a parser ici - le nom de fichier original n'a aucune utilite
 * pour Mustang et reste neutralise cote Symfony avant meme d'atteindre ce conteneur,
 * 10-security-privacy.md section 22). Chaque appel invoque Mustang-CLI comme sous-processus
 * isole (jamais dans le meme JVM que ce serveur) : Main.java de Mustang-CLI n'est pas garanti
 * de ne jamais appeler System.exit() sur tous ses chemins de code, un sous-processus isole
 * evite qu'un tel appel ne tue ce serveur HTTP.
 *
 * Signal a 3 branches (backend/App/Document/Service/MustangValidatorClient.php) porte
 * directement par le statut HTTP, jamais par un corps JSON a interpreter :
 *   - POST /extract : 200 + corps = XML Factur-X extrait (XML trouve) ;
 *                      204 (pas de corps) = aucun XML embarque (extraction propre, PAS une
 *                      erreur - Mustang-CLI confirme ecrire "No ZUGFeRD XML found in PDF
 *                      file" sur stderr et se terminer avec le code de sortie 0, vérifié sur
 *                      la source officielle Main.java au moment de l'implémentation) ;
 *                      502 = indisponibilite/erreur/timeout du sous-processus.
 *   - POST /validate : 200 + corps = rapport de validation XML (stdout de Mustang-CLI) ;
 *                      502 = indisponibilite/erreur/timeout.
 *
 * Mustang-CLI effectue son propre parsing XML interne (bibliotheque tierce encapsulee,
 * ADR-008) : ce wrapper ne fait lui-meme aucun parsing XML, seulement de la copie
 * d'octets/capture de flux.
 */
public final class MustangWrapper {

    private static final String MUSTANG_JAR = "/opt/mustang/Mustang-CLI.jar";
    private static final long PROCESS_TIMEOUT_SECONDS = 30;
    // Filet de securite redondant : Symfony impose deja 20 Mo (08-api-specification.md
    // section 31) avant meme d'atteindre ce conteneur - defense en profondeur, pas la
    // limite de reference.
    private static final long MAX_BODY_BYTES = 25L * 1024 * 1024;

    public static void main(String[] args) throws IOException {
        int port = 8080;
        HttpServer server = HttpServer.create(new InetSocketAddress(port), 0);
        server.createContext("/extract", new ActionHandler("extract"));
        server.createContext("/validate", new ActionHandler("validate"));
        server.setExecutor(Executors.newFixedThreadPool(4));
        server.start();
        System.out.println("mustang wrapper listening on :" + port);
    }

    private static final class ActionHandler implements HttpHandler {
        private final String action;

        ActionHandler(String action) {
            this.action = action;
        }

        @Override
        public void handle(HttpExchange exchange) throws IOException {
            if (!"POST".equalsIgnoreCase(exchange.getRequestMethod())) {
                respond(exchange, 405, null);
                return;
            }

            Path workDir = null;
            try {
                // Répertoire temporaire dédié, pas Files.createTempFile() pour la sortie :
                // "--action extract" de Mustang-CLI exige que le fichier "--out" n'existe PAS
                // encore (Main.ensureFileNotExists) - Files.createTempFile() le crée vide,
                // ce qui fait échouer l'appel (constaté au moment de l'implémentation,
                // corrigé explicitement ici). Un répertoire frais garantit un chemin de
                // sortie inexistant sans avoir à le pré-créer.
                workDir = Files.createTempDirectory("mustang-");
                Path input = workDir.resolve("in.bin");
                if (!copyBounded(exchange.getRequestBody(), input)) {
                    respond(exchange, 413, null);
                    return;
                }

                if ("extract".equals(action)) {
                    Path output = workDir.resolve("out.xml");
                    ProcessResult result = runCli(
                        "--action", "extract",
                        "--source", input.toString(),
                        "--out", output.toString(),
                        "--no-notices"
                    );

                    if (!result.completed || result.exitCode != 0) {
                        System.err.println("extract failed: completed=" + result.completed
                            + " exitCode=" + result.exitCode + " stderr=" + result.stderrText());
                        respond(exchange, 502, null);
                        return;
                    }

                    if (Files.exists(output) && Files.size(output) > 0) {
                        respond(exchange, 200, Files.readAllBytes(output));
                    } else {
                        // "No ZUGFeRD XML found in PDF file" (stderr, exit 0) - extraction
                        // propre, pas une erreur : voir le commentaire de tete de ce fichier.
                        respond(exchange, 204, null);
                    }
                    return;
                }

                if ("validate".equals(action)) {
                    ProcessResult result = runCli(
                        "--action", "validate",
                        "--source", input.toString(),
                        "--no-notices"
                    );

                    if (!result.completed || result.exitCode != 0) {
                        System.err.println("validate failed: completed=" + result.completed
                            + " exitCode=" + result.exitCode + " stderr=" + result.stderrText());
                        respond(exchange, 502, null);
                        return;
                    }

                    respond(exchange, 200, result.stdout);
                    return;
                }

                respond(exchange, 404, null);
            } catch (Exception e) {
                // Toujours journalisé sur stderr (docker compose logs mustang) : un 502
                // silencieux serait indiagnosticable depuis backend/worker, qui ne voit que
                // le statut HTTP (backend/CLAUDE.md section 12 - jamais de détail technique
                // exposé à l'utilisateur final, mais toujours journalisé côté opérateur).
                e.printStackTrace();
                respond(exchange, 502, null);
            } finally {
                deleteRecursivelyQuietly(workDir);
            }
        }
    }

    private static boolean copyBounded(InputStream in, Path destination) throws IOException {
        long total = 0;
        byte[] buffer = new byte[8192];
        try (OutputStream out = Files.newOutputStream(destination)) {
            int read;
            while ((read = in.read(buffer)) != -1) {
                total += read;
                if (total > MAX_BODY_BYTES) {
                    return false;
                }
                out.write(buffer, 0, read);
            }
        }
        return true;
    }

    private static final class ProcessResult {
        boolean completed;
        int exitCode;
        byte[] stdout;
        byte[] stderr;

        String stderrText() {
            return stderr != null ? new String(stderr, java.nio.charset.StandardCharsets.UTF_8) : "";
        }
    }

    private static ProcessResult runCli(String... cliArgs) throws IOException, InterruptedException {
        String[] command = new String[cliArgs.length + 3];
        command[0] = "java";
        command[1] = "-jar";
        command[2] = MUSTANG_JAR;
        System.arraycopy(cliArgs, 0, command, 3, cliArgs.length);

        ProcessBuilder builder = new ProcessBuilder(command);
        builder.redirectErrorStream(false);
        Process process = builder.start();

        // Capture le stdout pendant l'exécution (le rapport "validate" peut être volumineux),
        // jamais après un waitFor() qui bloquerait sur un buffer de pipe saturé.
        StreamCapture stdoutCapture = new StreamCapture(process.getInputStream());
        StreamCapture stderrCapture = new StreamCapture(process.getErrorStream());
        Thread stdoutThread = new Thread(stdoutCapture);
        Thread stderrThread = new Thread(stderrCapture);
        stdoutThread.start();
        stderrThread.start();

        ProcessResult result = new ProcessResult();
        result.completed = process.waitFor(PROCESS_TIMEOUT_SECONDS, TimeUnit.SECONDS);
        if (!result.completed) {
            process.destroyForcibly();
        }
        stdoutThread.join(2000);
        stderrThread.join(2000);

        result.exitCode = result.completed ? process.exitValue() : -1;
        result.stdout = stdoutCapture.toByteArray();
        result.stderr = stderrCapture.toByteArray();
        return result;
    }

    private static final class StreamCapture implements Runnable {
        private final InputStream source;
        private final java.io.ByteArrayOutputStream buffer = new java.io.ByteArrayOutputStream();

        StreamCapture(InputStream source) {
            this.source = source;
        }

        @Override
        public void run() {
            byte[] chunk = new byte[8192];
            try {
                int read;
                while ((read = source.read(chunk)) != -1) {
                    buffer.write(chunk, 0, read);
                }
            } catch (IOException ignored) {
                // Flux fermé au timeout (process.destroyForcibly()) - pas une erreur à
                // remonter, le statut 502 est déjà décidé par ProcessResult.completed.
            }
        }

        byte[] toByteArray() {
            return buffer.toByteArray();
        }
    }

    private static void respond(HttpExchange exchange, int status, byte[] body) throws IOException {
        byte[] payload = body != null ? body : new byte[0];
        exchange.getResponseHeaders().set("Content-Type", "application/xml; charset=utf-8");
        exchange.sendResponseHeaders(status, payload.length == 0 ? -1 : payload.length);
        if (payload.length > 0) {
            try (OutputStream out = exchange.getResponseBody()) {
                out.write(payload);
            }
        } else {
            exchange.getResponseBody().close();
        }
    }

    private static void deleteRecursivelyQuietly(Path directory) {
        if (directory == null) {
            return;
        }
        try (var paths = Files.walk(directory)) {
            paths.sorted(java.util.Comparator.reverseOrder())
                .forEach(path -> {
                    try {
                        Files.deleteIfExists(path);
                    } catch (IOException ignored) {
                        // Best effort - un fichier temporaire résiduel n'est pas une donnée
                        // sensible exploitable à distance (conteneur isolé, jamais exposé),
                        // mais ne doit jamais faire échouer la réponse HTTP déjà décidée.
                    }
                });
        } catch (IOException ignored) {
            // Idem : le répertoire a pu déjà disparaître (ex. échec avant sa création).
        }
    }

    private MustangWrapper() {
    }
}
