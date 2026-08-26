# Nginx de production - FactuSentinel

Phase 17 (`docs/12-roadmap.md`). Couvre le reverse proxy TLS de production
(`docker/nginx/prod.conf.template`, overlay `docker-compose.prod.yml`) - distinct de
`docker/nginx/default.conf` (dev/beta, tunnel Cloudflare, jamais de TLS terminé par
Nginx lui-même).

**Périmètre de cette phase** : configuration et scripts génériques, indépendants de
l'hébergeur retenu (OVHcloud, `docs/12-roadmap.md` Phase 17 - toute vérification finale
de l'offre reste à faire au moment du provisionnement réel). L'exécution réelle
(domaine, DNS, premier certificat) relève du Bloc B, jamais anticipée ici.

## Modèle TLS

Certificats Let's Encrypt, méthode HTTP-01 par webroot (`certbot/certbot`, service
`certbot` de `docker-compose.prod.yml`, jamais démarré par `up -d` - profil `certbot`
dédié, invoqué explicitement via les scripts ci-dessous). Renouvellement par rechargement
de Nginx (`nginx -s reload`), jamais un redémarrage complet.

## Problème d'amorçage et solution retenue

Nginx résout `ssl_certificate`/`ssl_certificate_key` au chargement de sa configuration,
pas à la requête : il **refuse de démarrer** si ces fichiers n'existent pas encore, ce
qui est le cas pour un tout premier déploiement sur un nouveau domaine (le certificat
réel ne peut être obtenu qu'en répondant au défi HTTP-01, qui exige que Nginx soit déjà
en train de servir `/.well-known/acme-challenge/`). Solution retenue, standard pour ce
patron Nginx + Certbot + Docker Compose : un certificat auto-signé temporaire au même
chemin permet à Nginx de démarrer, le vrai certificat est obtenu pendant que Nginx tourne
déjà, puis Nginx est rechargé avec le certificat réel - automatisé par
`bootstrap-cert.sh`.

## Émission initiale (une seule fois par domaine)

```bash
docker/nginx/bootstrap-cert.sh app.exemple.fr contact@exemple.fr
```

Préalable : le domaine doit déjà pointer (enregistrement DNS A/AAAA) vers l'IP publique
du serveur, et les ports 80/443 doivent être joignables depuis Internet (jamais bloqués
par un pare-feu - le défi HTTP-01 échoue silencieusement sinon, vérifié sur la
documentation officielle Certbot).

## Renouvellement périodique

```bash
docker/nginx/renew-cert.sh
```

À automatiser via cron/systemd timer côté hôte, cohérent avec l'automatisation prévue
pour `docker/backup/backup.sh` (Phase 17, même principe : aucun démon supplémentaire dans
la stack Docker, l'ordonnancement reste au niveau du système hôte). Idempotent - Certbot
ne renouvelle réellement qu'à l'approche de l'expiration (30 jours avant, par défaut).

## Ce qui reste au Bloc B

- Domaine réel, DNS pointant vers le serveur provisionné.
- `PUBLIC_DOMAIN`/`ACME_EMAIL` réels dans `.env.production`/`.env.staging` (voir
  `.env.prod.example`).
- Exécution réelle de `bootstrap-cert.sh`, puis mise en place du cron/systemd timer pour
  `renew-cert.sh`.
- Activation de `HSTS_ENABLED=true` (`backend/.env`, `HstsHeaderListener`) **seulement**
  une fois le certificat réel confirmé actif - jamais avant, au risque de rendre le site
  inaccessible en HTTPS pour les navigateurs l'ayant déjà mémorisé.
