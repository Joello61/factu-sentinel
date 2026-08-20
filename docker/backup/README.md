# Sauvegarde et restauration - FactuSentinel

Phase 10 (Security & Privacy Hardening, `docs/12-roadmap.md`). Couvre PostgreSQL et le
stockage documentaire local (`backend/var/storage/documents`) - jamais l'un sans l'autre,
pour ne jamais laisser un `Document` sans son fichier ou l'inverse
(`docs/06-technical-architecture.md`, section 32).

**Périmètre de cette phase** : mécanisme de sauvegarde/restauration et preuve qu'il
fonctionne réellement (local, Docker Compose). L'automatisation périodique (cron/systemd
timer réel, rétention, stockage hors site) dépend d'un hébergeur non encore choisi et
relève de la Phase 13 - voir `docs/10-security-privacy.md`, section 68 (checklist), item
`DEFERRED - Phase 13 - requires hosted infrastructure`.

## RPO / RTO

24h / 24h (décision produit actée, `docs/10-security-privacy.md` section 59) - une
fréquence de sauvegarde au moins quotidienne est nécessaire pour tenir cet engagement une
fois en production ; non automatisée ici (voir ci-dessus).

## Gestion de la clé de chiffrement

`BACKUP_GPG_PASSPHRASE` (variable d'environnement) - **ne doit jamais** :

- être committée dans le dépôt ;
- être stockée dans le même répertoire, volume ou archive que la sauvegarde qu'elle
  protège (un backup chiffré dont la clé est à côté n'est pas une sauvegarde sécurisée,
  `docs/10-security-privacy.md` sections 26-27) ;
- être réutilisée entre environnements (section 53 du même document).

Au stade actuel (développeur solo, environnement local), la conserver dans un
gestionnaire de mots de passe personnel ou un coffre-fort de secrets, jamais dans
`backend/.env`/`backend/.env.local` ni dans `backups/`. La gestion/rotation automatisée
d'une clé de production reste un sujet Phase 13 (aucun mécanisme de gestion de clé
d'infrastructure n'existe encore).

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
