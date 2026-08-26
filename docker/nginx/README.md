# Nginx de production - FactuSentinel

Phase 17 (`docs/12-roadmap.md`). Couvre le pont HTTP -> FastCGI interne de production
(`docker/nginx/prod.conf.template`, overlay `docker-compose.prod.yml`) - distinct de
`docker/nginx/default.conf` (dev/beta, tunnel Cloudflare).

**Périmètre de cette phase** : configuration générique, indépendante de l'hébergeur
retenu (OVHcloud, `docs/12-roadmap.md` Phase 17). L'exécution réelle (domaine, DNS,
premier certificat) relève du Bloc B - voir le runbook serveur Phase 17 pour la procédure
complète, jamais anticipée ici.

## Rôle de Nginx depuis l'introduction de Traefik

Nginx n'est plus l'edge public de FactuSentinel. Traefik (infrastructure de niveau
serveur, partagée entre tous les projets hébergés sur le même VPS, hors de ce dépôt)
termine le TLS et route par nom de domaine (`Host()`) vers ce service, en HTTP interne
sur le réseau Docker externe `traefik-public` (`docker-compose.prod.yml`, service
`nginx` - ni port publié, ni certificat local).

Nginx reste dans la stack pour une seule raison technique : Traefik n'a pas de
fournisseur FastCGI, alors que `location /api/` proxifie vers `backend:9000` (PHP-FPM) en
FastCGI, pas en HTTP. Nginx est donc le pont HTTP (venant de Traefik) -> FastCGI (vers
PHP-FPM) - son seul rôle restant, avec le routage `/` vers le frontend Next.js.

## Ce qui a disparu avec Traefik

- Le service `certbot` et les volumes `certbot_conf`/`certbot_webroot`
  (`docker-compose.prod.yml`) : Traefik émet et renouvelle lui-même les certificats
  Let's Encrypt (résolveur ACME configuré dans la stack Traefik partagée), aucune
  automatisation applicative n'est plus nécessaire côté FactuSentinel.
- `docker/nginx/bootstrap-cert.sh`/`renew-cert.sh` : supprimés, le problème d'amorçage
  qu'ils résolvaient (Nginx refusant de démarrer sans certificat déjà présent) n'existe
  plus - Nginx ne référence plus aucun certificat, il sert en HTTP interne uniquement.
- `ACME_EMAIL` dans `.env.staging`/`.env.production` : devenu une variable de la stack
  Traefik partagée (une seule adresse de contact pour tous les projets hébergés), plus
  une variable par projet.

## TLS, DNS, certificat réel

Entièrement porté par Traefik désormais - voir le runbook serveur Phase 17 (installation
Traefik, résolveur ACME, labels de routage sur ce service `nginx`) pour la procédure
complète d'émission du premier certificat et son renouvellement automatique.

## Ce qui reste au Bloc B

- Domaine réel, DNS pointant vers le serveur provisionné.
- `PUBLIC_DOMAIN` réel dans `.env.production`/`.env.staging` (voir `.env.prod.example`).
- Installation et configuration de Traefik sur le serveur (runbook Phase 17).
- Activation de `HSTS_ENABLED=true` (`backend/.env`, `HstsHeaderListener`) **seulement**
  une fois le certificat réel confirmé actif côté Traefik - jamais avant, au risque de
  rendre le site inaccessible en HTTPS pour les navigateurs l'ayant déjà mémorisé.
- Vérification qu'un composant applicatif décidant d'activer HSTS selon le schéma de la
  requête lit bien l'en-tête `X-Forwarded-Proto` transmis par Nginx (posé par Traefik à
  l'edge), pas une détection basée sur une connexion TLS locale à Nginx qui n'existe
  plus.
