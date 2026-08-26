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

# Limitation historique résolue (Phase 17, vérifié le 26/08/2026) : "next build" échouait
# de façon déterministe sur Next.js 16.0.x-16.3.1 lors de la pré-génération de
# "/_global-error" (vercel/next.js#86178, #84994, #95741), masqué par une erreur
# TypeScript indépendante et pré-existante dans app/(app)/company/CompanyForm.test.tsx qui
# faisait échouer le build encore plus tôt. Les deux sont corrigés : le bug amont ne se
# reproduit plus sur 16.3.3 (vérifié par un "next build" et une image Docker complets,
# conteneur démarré et testé), et l'erreur TypeScript était une inférence de contrôle de
# flux TypeScript sur "let x: T | null = null" réassigné dans une closure - corrigée en
# remplaçant l'initialisation par "| undefined" non initialisé.
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

# node -e plutôt que curl/wget : l'image "alpine" ne les installe pas par défaut, jamais
# ajoutés uniquement pour cette vérification (section 21 de ../CLAUDE.md). /login est
# public (frontend/proxy.ts, ALWAYS_ACCESSIBLE_PATHS), donc atteignable sans session.
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD node -e "require('http').get('http://127.0.0.1:3000/login',r=>process.exit(r.statusCode<500?0:1)).on('error',()=>process.exit(1))"

CMD ["node", "server.js"]
