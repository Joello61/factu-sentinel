<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 13 - Paramètres & Profil utilisateur (docs/12-roadmap.md ; docs/07-data-model.md,
 * section 30) : soft delete sur users, nécessaire à DELETE /users/current (US-SETTINGS-002).
 *
 * L'ancien index unique `uniq_user_email` (Phase 1) portait sur toute la table, ce qui aurait
 * empêché la ré-inscription avec l'email d'un compte soft-deleted (constaté en testant
 * US-SETTINGS-002 - voir plan Phase 13). Remplacé par un index unique partiel
 * `WHERE deleted_at IS NULL`, même patron que `uniq_fiscal_contexts_current_per_organization`
 * (Phase 3, Version20260819121231) : App\Identity\Entity\User n'exprime plus cette contrainte
 * via #[ORM\UniqueConstraint] (le driver attribute de Doctrine ORM ne supporte pas les index
 * partiels), elle vit uniquement ici.
 */
final class Version20260821110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 13 - users.deleted_at (soft delete, US-SETTINGS-002) + index unique email partiel.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('DROP INDEX uniq_user_email');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email) WHERE (deleted_at IS NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_user_email');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email)');
        $this->addSql('ALTER TABLE users DROP deleted_at');
    }
}
