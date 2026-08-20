# FactuSentinel

Assistant de conformité à la facturation électronique pour les micro-entrepreneurs, indépendants, freelances et TPE françaises.

## Le projet

La France généralise la facturation électronique entre entreprises assujetties à la TVA (réforme e-invoicing / e-reporting, réception obligatoire pour toutes les entreprises au 1er septembre 2026, émission obligatoire pour les PME/TPE/micro-entreprises au 1er septembre 2027). Les petites structures visées par FactuSentinel n'ont généralement ni service comptable ni service informatique dédié pour anticiper ce changement.

FactuSentinel n'est ni un logiciel de facturation, ni un logiciel comptable, ni une plateforme agréée. C'est un **assistant de préparation, de contrôle et de compréhension de la conformité** : il aide à déterminer les obligations applicables, analyse des factures existantes, explique les non-conformités détectées en langage clair (règle, source, conséquence, correction recommandée), et accompagne l'utilisateur jusqu'à l'utilisation de sa propre plateforme agréée pour l'émission réelle.

Le moteur de conformité (Compliance Engine) est déterministe et versionné : chaque résultat reste traçable jusqu'à la règle réglementaire précise qui le justifie. Une couche d'IA (Mistral) reformule ces résultats en langage pédagogique, mais ne détermine jamais elle-même une conformité.

## État du projet

En développement actif, phase de conception achevée. Voir `docs/12-roadmap.md` pour le séquencement détaillé (phases, milestones M0-M7) et l'état d'avancement réel.

## Stack technique

| Couche                          | Choix                                |
| ------------------------------- | ------------------------------------ |
| Frontend                        | Next.js, TypeScript, Tailwind CSS v4 |
| Backend                         | Symfony (PHP), monolithe modulaire   |
| Base de données                 | PostgreSQL                           |
| Traitement asynchrone / cache   | Redis                                |
| Validation Factur-X / UBL / CII | Mustangproject (conteneur isolé)     |
| IA                              | Mistral                              |
| Infrastructure                  | Docker, Nginx                        |
| CI/CD                           | GitHub Actions                       |

Détail complet et justifications architecturales : `docs/06-technical-architecture.md`.

## Structure du dépôt

```text
.
├── backend/     API Symfony (PHP) - voir backend/CLAUDE.md
├── frontend/    Application Next.js - voir frontend/CLAUDE.md
├── docker/      Configuration de conteneurisation (en construction)
├── infra/       Configuration d'infrastructure (en construction)
└── docs/        Documentation de conception - source de vérité du projet
```

## Documentation

Le dossier `docs/` est la source de vérité fonctionnelle, réglementaire, architecturale et produit du projet. À lire avant toute contribution significative :

| Document                            | Contenu                                                 |
| ----------------------------------- | ------------------------------------------------------- |
| `docs/01-intent-note.md`            | Vision, cible, positionnement, hors périmètre           |
| `docs/02-regulatory-study.md`       | Réglementation française de la facturation électronique |
| `docs/03-market-analysis.md`        | Marché, concurrence, hypothèses                         |
| `docs/04-product-requirements.md`   | Exigences produit, Business Rules, décisions produit    |
| `docs/05-user-stories.md`           | Parcours utilisateurs, critères d'acceptation           |
| `docs/06-technical-architecture.md` | Architecture, modules, décisions d'architecture (ADR)   |
| `docs/07-data-model.md`             | Modèle de données                                       |
| `docs/08-api-specification.md`      | Contrats de l'API                                       |
| `docs/09-test-strategy.md`          | Stratégie de test                                       |
| `docs/10-security-privacy.md`       | Sécurité et confidentialité (RGPD)                      |
| `docs/11-frontend-design-system.md` | Système de design                                       |
| `docs/12-roadmap.md`                | Séquencement, phases, milestones                        |

Les règles de développement (architecture, sécurité, réglementation, IA, Git, workflow) sont définies dans `CLAUDE.md` à la racine, complété par `backend/CLAUDE.md` et `frontend/CLAUDE.md`.

## Démarrage

### Prérequis

- PHP >= 8.4, Composer
- Node.js (voir `frontend/package.json` pour la version des dépendances)
- PostgreSQL, Redis (conteneurisation Docker prévue, non encore en place - voir `docs/12-roadmap.md`, Phase 0-1)

### Backend

```bash
cd backend
composer install
php bin/console server:start   # ou symfony server:start
```

### Frontend

```bash
cd frontend
npm install
npm run dev
```

Ces instructions reflètent l'état actuel (squelettes Symfony et Next.js) et évolueront avec la mise en place de l'environnement Docker Compose (PostgreSQL, Redis, Nginx) prévue en Phase 0-1 de la roadmap.

## Licence

Distribué sous licence MIT - voir [LICENSE](LICENSE).
