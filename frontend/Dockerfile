# syntax=docker/dockerfile:1
#
# Image Docker du frontend Next.js.
# Node 22 (LTS active) : Next.js 16 exige au minimum Node 20.9 ; vérifier la version
# recommandée au moment d'une montée de version majeure de Next.js.
#
# Trois cibles ("--target") :
#   dev     - utilisée par docker-compose.yml, code source monté en volume, "next dev"
#   builder - construit le build de production ("next build", sortie standalone)
#   prod    - image finale minimale ("node server.js"), utilisateur non-root

FROM node:22-alpine AS base
WORKDIR /app

# ---------------------------------------------------------------------------
FROM base AS deps

COPY package.json package-lock.json* ./
RUN npm ci

# ---------------------------------------------------------------------------
FROM deps AS dev

ENV NODE_ENV=development

# Le code source réel est monté en volume par docker-compose.yml en développement ;
# cette copie garantit néanmoins que l'image reste utilisable seule (ex. CI).
COPY . .

COPY docker/entrypoint-dev.sh /usr/local/bin/docker-entrypoint-dev.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-dev.sh

ENTRYPOINT ["docker-entrypoint-dev.sh"]
EXPOSE 3000
CMD ["npm", "run", "dev"]

# ---------------------------------------------------------------------------
FROM deps AS builder

ENV NODE_ENV=production

COPY . .

# LIMITATION CONNUE (Next.js 16.0.x à 16.3.1, dernière stable au moment de l'écriture) :
# "next build" échoue de façon déterministe lors de la pré-génération de la page interne
# "/_global-error" ("Cannot read properties of null (reading 'useContext')"), y compris sur
# une application sans providers ni composants custom (bug du framework, pas de ce projet
# - vérifié en isolant le code applicatif et en testant --webpack, tous deux affectés
# identiquement). Bug amont non résolu au 19/08/2026 : vercel/next.js#86178, #84994,
# #95741. "next dev" (utilisé par docker-compose.yml) n'est pas affecté. Cette image ne
# doit pas être considérée prête pour un déploiement de production tant que ce correctif
# n'est pas publié en amont ou qu'une décision de stack explicite (ADR) n'est prise.
RUN npm run build

# ---------------------------------------------------------------------------
FROM base AS prod

ENV NODE_ENV=production \
    PORT=3000 \
    HOSTNAME=0.0.0.0

RUN addgroup --system --gid 1001 nodejs \
    && adduser --system --uid 1001 nextjs

# Sortie "standalone" (next.config.ts, output: "standalone") : ne copie que le strict
# nécessaire à l'exécution, sans node_modules complet.
COPY --from=builder /app/public ./public
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static

USER nextjs

EXPOSE 3000
CMD ["node", "server.js"]
