# Sauvegarde et restauration - FactuSentinel

Phase 10 (Security & Privacy Hardening, `docs/12-roadmap.md`). Couvre PostgreSQL et le
stockage documentaire local (`backend/var/storage/documents`) - jamais l'un sans l'autre,
pour ne jamais laisser un `Document` sans son fichier ou l'inverse
(`docs/06-technical-architecture.md`, section 32).

**Historique** : le mécanisme de sauvegarde/restauration lui-même (`backup.sh`/
`restore.sh`) et la preuve qu'il fonctionne réellement datent de la Phase 10 (local,
Docker Compose). L'automatisation périodique (cron/systemd timer réel, rétention,
stockage hors site, `automated-backup.sh` ci-dessous) est ajoutée en Phase 17
(`docs/12-roadmap.md`), une fois l'hébergeur confirmé (OVHcloud) - voir
`docs/10-security-privacy.md` section 68.

**Correctif Phase 17** : `backup.sh`/`restore.sh` lisaient/écrivaient auparavant
directement `backend/var/storage/documents` sur l'hôte, ce qui ne fonctionne qu'en
développement (bind-mount, `docker-compose.yml`). En production
(`docker-compose.prod.yml`), ce chemin est porté par un volume Docker nommé
(`storage_documents`), jamais accessible directement depuis l'hôte - les deux scripts
passent désormais par `docker compose exec backend tar ...` dans les deux cas, jamais un
chemin hôte supposé.

**Hors périmètre (Phase 18, décision explicite)** : les données de la stack d'observabilité
(`docker/observability/` - Loki, Prometheus, Tempo) ne rejoignent jamais cette sauvegarde.
Ce sont des données d'observabilité, pas des données métier - entièrement reproductibles
depuis le provisioning Grafana (as-code, `docker/observability/grafana/provisioning/`) et
sans perte de valeur métier en cas de perte, contrairement à PostgreSQL/`storage_documents`
ci-dessus.

## RPO / RTO

24h / 24h (décision produit actée, `docs/10-security-privacy.md` section 59) - une
fréquence de sauvegarde au moins quotidienne est nécessaire pour tenir cet engagement une
fois en production, assurée par `automated-backup.sh` ci-dessous une fois le cron/systemd
timer en place (Bloc B, provisionnement réel).

## Gestion de la clé de chiffrement

`BACKUP_GPG_PASSPHRASE` (variable d'environnement) - **ne doit jamais** :

- être committée dans le dépôt ;
- être stockée dans le même répertoire, volume ou archive que la sauvegarde qu'elle
  protège (un backup chiffré dont la clé est à côté n'est pas une sauvegarde sécurisée,
  `docs/10-security-privacy.md` sections 26-27) ;
- être réutilisée entre environnements (section 53 du même document).

En développement local, la conserver dans un gestionnaire de mots de passe personnel ou
un coffre-fort de secrets, jamais dans `backend/.env`/`backend/.env.local` ni dans
`backups/`. En production, l'injecter dans l'environnement d'exécution du cron/systemd
timer via le mécanisme de secrets de l'hébergeur (jamais en dur dans une crontab
committée ou un fichier `.env` versionné) - une valeur **distincte** de celle utilisée en
développement/staging (section 53, jamais de secret réutilisé entre environnements).

## Sauvegarde

```bash
BACKUP_GPG_PASSPHRASE='...' docker/backup/backup.sh [répertoire-de-sortie]
```

Produit `<répertoire-de-sortie>/factusentinel-backup-<horodatage>.tar.gpg` (par défaut,
`backups/` à la racine du dépôt - déjà couvert par une règle `.gitignore` à ajouter si ce
répertoire est utilisé localement, pour ne jamais committer une sauvegarde réelle).

## Restauration

```bash
BACKUP_GPG_PASSPHRASE='...' docker/backup/restore.sh <archive.tar.gpg> [--yes]
```

**Destructif** : recrée la base de données cible et remplace intégralement
`backend/var/storage/documents`. Confirmation interactive requise sauf `--yes`.

## Automatisation périodique (Phase 17)

`automated-backup.sh` enchaîne : sauvegarde ciblant la stack de production/staging (pas
la stack de dev par défaut), envoi de l'archive vers un stockage objet distant
compatible S3 (rclone - fonctionne avec OVHcloud Object Storage comme avec tout autre
fournisseur compatible S3, jamais un outil propriétaire à un hébergeur précis), puis
rétention locale et distante.

Prérequis, une seule fois sur le serveur :

```bash
# Installation (voir la documentation officielle rclone pour la méthode actuelle -
# https://rclone.org/install/ - vérifier avant d'exécuter un script d'installation
# tiers).
rclone config
# Créer un remote nommé (ex. "ovh-backup") pointant vers le stockage objet retenu -
# assistant interactif, type "s3", endpoint et identifiants du fournisseur.
```

Exemple de tâche cron (à adapter - systemd timer équivalent possible, même principe) :

```cron
# Sauvegarde quotidienne à 3h locales - les secrets sont chargés depuis un fichier non
# committé (permissions 600), jamais écrits directement dans la crontab.
0 3 * * * . /etc/factusentinel/backup.env && /opt/factusentinel/docker/backup/automated-backup.sh >> /var/log/factusentinel-backup.log 2>&1
```

`/etc/factusentinel/backup.env` (jamais committé, permissions restreintes) :

```bash
BACKUP_GPG_PASSPHRASE='...'
BACKUP_RCLONE_REMOTE='ovh-backup:factusentinel-backups'
BACKUP_RETENTION_DAYS='14'
COMPOSE_ENV_FILE='.env.production'
# Optionnel - voir docker/monitoring/README.md (moniteur "push" Uptime Kuma) : détecte une
# sauvegarde silencieusement absente, jamais un échec explicite signalé par ce script.
BACKUP_MONITORING_PUSH_URL='https://monitoring.exemple/api/push/...'
```

## Test de restaurabilité (à exécuter, pas seulement documenter)

Une sauvegarde non testée n'est pas une garantie fiable (`docs/10-security-privacy.md`,
section 54). Procédure vérifiée manuellement pour cette phase, sur l'environnement Docker
Compose local :

1. Créer des données de test (compte, organisation, client, facture, upload d'un
   document).
2. `docker/backup/backup.sh`.
3. Détruire les volumes locaux (`docker compose down -v` puis `docker compose up -d`,
   migrations rejouées).
4. `docker/backup/restore.sh <archive> --yes`.
5. Vérifier la **cohérence croisée**, pas seulement la présence de données :
   - chaque `Document.storage_reference` pointe vers un fichier réellement présent sur
     `backend/var/storage/documents` après restauration ;
   - `Invoice` ↔ `Document` ↔ `DocumentProcessingRecord` restent cohérents entre eux
     (aucune référence orpheline dans un sens ou dans l'autre).
