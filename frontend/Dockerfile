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

EXPOSE 3000
CMD ["npm", "run", "dev"]

# ---------------------------------------------------------------------------
FROM deps AS builder

ENV NODE_ENV=production

COPY . .

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
