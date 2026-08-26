<?php

declare(strict_types=1);

namespace App\Document\Service;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Implémentation ClamAV du scan antivirus (Phase 17, docs/12-roadmap.md), isolée dans son
 * propre conteneur ("clamav", docker-compose.yml) et jamais accessible autrement que par le
 * réseau Docker interne - même principe d'isolation que le Validator Container Mustang
 * (ADR-008), bien que le protocole diffère (INSTREAM de clamd, jamais HTTP - vérifié sur la
 * documentation officielle ClamAV le 26/08/2026, docs.clamav.net/manual/Usage/ClamdProtocol.html).
 *
 * Politique fail-closed délibérée (docs/10-security-privacy.md section 3, Fail Secure) : une
 * indisponibilité de clamd est un 503 (l'upload est refusé, jamais silencieusement laissé
 * passer sans scan) - à la différence de Mustang, dont l'indisponibilité n'empêche jamais
 * l'affichage d'un résultat déjà produit (rôles différents : Mustang enrichit une analyse
 * déjà persistée, ce scanner conditionne si le contenu est persisté du tout).
 */
final class ClamAvScanner implements AntivirusScannerInterface
{
    private const string COMMAND = "zINSTREAM\0";
    private const int CHUNK_SIZE = 8192;
    private const float TIMEOUT_SECONDS = 15.0;

    public function __construct(
        private readonly string $clamAvHost,
        private readonly int $clamAvPort,
    ) {
    }

    public function scan(string $content): void
    {
        $socket = @stream_socket_client(
            \sprintf('tcp://%s:%d', $this->clamAvHost, $this->clamAvPort),
            $errno,
            $errstr,
            self::TIMEOUT_SECONDS,
        );

        if (false === $socket) {
            throw new HttpException(503, 'Service de scan antivirus indisponible.');
        }

        try {
            stream_set_timeout($socket, (int) self::TIMEOUT_SECONDS);

            $this->write($socket, self::COMMAND);

            foreach (str_split($content, self::CHUNK_SIZE) as $chunk) {
                $this->write($socket, pack('N', \strlen($chunk)).$chunk);
            }
            // Chunk de taille nulle : signale la fin du flux (protocole INSTREAM).
            $this->write($socket, pack('N', 0));

            $response = stream_get_contents($socket);
            if (false === $response || '' === $response) {
                throw new HttpException(503, 'Service de scan antivirus indisponible.');
            }
        } finally {
            fclose($socket);
        }

        $response = rtrim($response, "\0\n");

        if (str_ends_with($response, 'OK')) {
            return;
        }

        if (str_contains($response, 'FOUND')) {
            // Le nom de signature (ex. "Eicar-Signature FOUND") n'est jamais renvoyé au
            // client - un message générique suffit, cohérent avec le reste de
            // UploadedDocumentValidator (jamais de détail technique interne exposé,
            // backend/CLAUDE.md section 12).
            throw new UnprocessableEntityHttpException('Le fichier importé a été refusé par le scan antivirus.');
        }

        // Réponse ni "OK" ni "FOUND" (ex. "ERROR", flux tronqué) : traité comme une
        // indisponibilité, jamais comme un fichier propre par défaut.
        throw new HttpException(503, 'Service de scan antivirus indisponible.');
    }

    /**
     * @param resource $socket
     */
    private function write($socket, string $data): void
    {
        $length = \strlen($data);
        $written = 0;

        while ($written < $length) {
            $result = @fwrite($socket, substr($data, $written));
            if (false === $result || 0 === $result) {
                throw new HttpException(503, 'Service de scan antivirus indisponible.');
            }
            $written += $result;
        }
    }
}
