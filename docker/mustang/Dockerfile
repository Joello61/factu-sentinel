# Validator Container (ADR-008, docs/06-technical-architecture.md section 11/30) : isole du
# runtime PHP, jamais integre directement - conteneur Java independant, appele par
# "backend"/"worker" uniquement sur le reseau Docker interne (docker-compose.yml, aucun
# "ports:" publie).
#
# Mustang-CLI (org.mustangproject:Mustang-CLI, https://github.com/ZUGFeRD/mustangproject,
# licence Apache-2.0) : version et somme de controle verifiees le jour de l'implementation
# de la Phase 7 (docs/12-roadmap.md) directement depuis Maven Central - a revalider si ce
# Dockerfile est repris longtemps apres cette date (../../CLAUDE.md racine, section 3).

# ---------------------------------------------------------------------------
# Etape 1 : compilation du wrapper HTTP (nécessite un JDK, absent de l'image finale).
FROM eclipse-temurin:21-jdk-alpine AS build

WORKDIR /build
COPY src/MustangWrapper.java .
RUN javac MustangWrapper.java

# ---------------------------------------------------------------------------
# Etape 2 : image finale, JRE seul (jamais de JDK/compilateur en production - surface
# d'attaque minimale, coherent avec le principe de moindre privilege sur un conteneur qui
# traite des fichiers non fiables, docs/10-security-privacy.md section 3).
FROM eclipse-temurin:21-jre-alpine

RUN addgroup -S mustang && adduser -S mustang -G mustang

WORKDIR /opt/mustang

# Somme de controle figee (calculee depuis le jar officiel Maven Central le jour de
# l'implementation) : la construction de l'image echoue si le jar telecharge ne correspond
# plus exactement a cette somme, plutot que de faire confiance silencieusement au reseau/CDN
# a chaque build (defense en profondeur sur une dependance externe, ../../CLAUDE.md racine
# section 21).
ARG MUSTANG_CLI_VERSION=2.25.0
ARG MUSTANG_CLI_SHA256=018105ef04ece7f77a7fc2a9fb8fb570c6931494a5702333407ec6c9bd1ac4ec

RUN wget -q -O Mustang-CLI.jar \
        "https://repo1.maven.org/maven2/org/mustangproject/Mustang-CLI/${MUSTANG_CLI_VERSION}/Mustang-CLI-${MUSTANG_CLI_VERSION}.jar" \
    && echo "${MUSTANG_CLI_SHA256}  Mustang-CLI.jar" | sha256sum -c - \
    && chown mustang:mustang Mustang-CLI.jar

# *.class (pas seulement MustangWrapper.class) : javac génère un fichier par classe imbriquée
# (ActionHandler, ProcessResult, StreamCapture) - un COPY trop étroit ici a échoué au moment
# de l'implémentation (NoClassDefFoundError au démarrage), corrigé explicitement.
COPY --from=build /build/*.class /opt/mustang-wrapper/
RUN chown -R mustang:mustang /opt/mustang-wrapper

USER mustang

EXPOSE 8080

# Pas de healthcheck HTTP applicatif ici : le conteneur n'est jamais exposé publiquement, la
# détection d'indisponibilité (timeout/erreur de connexion) est gérée côté appelant
# (MustangValidatorClient.php, backend/CLAUDE.md section 12 - erreur technique explicite,
# jamais un résultat de conformité).
CMD ["java", "-cp", "/opt/mustang-wrapper", "MustangWrapper"]
