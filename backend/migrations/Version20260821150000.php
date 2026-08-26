<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 15 (docs/12-roadmap.md) : App\PlatformAdmin\Entity\PlatformAdministrator,
 * App\PlatformAdmin\Entity\PlatformAdminMfaChallenge (ticket MFA à usage unique, revue
 * utilisateur du 21/08/2026), App\AI\Entity\AiCallLogEntry, App\Organization\Entity\Organization::$suspendedAt,
 * App\Notification\Entity\Notification révisée (organization_id devient nullable - portée
 * cross-tenant d'une notification PLATFORM_ADMIN, voir cette entité - et nouvelle colonne
 * platform_admin_sender_id, référence opaque jamais une relation Doctrine vers
 * platform_administrators, même patron que source_diagnostic_id).
 *
 * Élaguée manuellement du diff auto-généré par `doctrine:migrations:diff` (même vigilance que
 * les migrations Phase 3/13/14 sur les index uniques partiels créés en SQL brut, non
 * reconnus par le comparateur de schéma Doctrine) - aucun index partiel touché par cette
 * phase, vérifié avant application.
 */
final class Version20260821150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 15 : PlatformAdministrator, AiCallLogEntry, Organization.suspended_at, Notification cross-tenant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE platform_administrators (
              id UUID NOT NULL,
              email VARCHAR(180) NOT NULL,
              password VARCHAR(255) NOT NULL,
              totp_secret TEXT NOT NULL,
              totp_confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_platform_administrators_email ON platform_administrators (email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE platform_admin_mfa_challenges (
              id UUID NOT NULL,
              platform_administrator_id UUID NOT NULL,
              token_selector VARCHAR(32) NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_platform_admin_mfa_challenges_token_selector ON platform_admin_mfa_challenges (token_selector)');
        $this->addSql('CREATE INDEX idx_platform_admin_mfa_challenges_administrator_id ON platform_admin_mfa_challenges (platform_administrator_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              platform_admin_mfa_challenges
            ADD
              CONSTRAINT fk_platform_admin_mfa_challenges_administrator_id FOREIGN KEY (platform_administrator_id) REFERENCES platform_administrators (id) NOT DEFERRABLE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE ai_call_log_entries (
              id UUID NOT NULL,
              organization_id UUID NOT NULL,
              endpoint VARCHAR(255) NOT NULL,
              succeeded BOOLEAN NOT NULL,
              estimated_cost NUMERIC(10, 4) DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_ai_call_log_entries_organization_id ON ai_call_log_entries (organization_id)');
        $this->addSql('CREATE INDEX idx_ai_call_log_entries_created_at ON ai_call_log_entries (created_at)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              ai_call_log_entries
            ADD
              CONSTRAINT fk_ai_call_log_entries_organization_id FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE
        SQL);

        $this->addSql('ALTER TABLE organizations ADD suspended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // Notification (docs/07-data-model.md, section 21, révision Phase 15) : organization_id
        // devient nullable (notification PLATFORM_ADMIN, portée cross-tenant) - la contrainte
        // FK existante (FK_6000B0D332C8A3DE, Phase 14) reste valide sur une colonne nullable
        // sans modification, PostgreSQL l'autorise nativement pour une FK ON DELETE non
        // définie explicitement (NO ACTION par défaut).
        $this->addSql('ALTER TABLE notifications ALTER COLUMN organization_id DROP NOT NULL');
        $this->addSql('ALTER TABLE notifications ADD platform_admin_sender_id UUID DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notifications DROP COLUMN platform_admin_sender_id');
        // Une notification déjà émise avec organization_id NULL empêcherait ce rollback de
        // repasser la colonne en NOT NULL - acceptable pour une migration down de
        // développement (docs/backend/CLAUDE.md, section 7 : jamais de modification manuelle
        // de schéma en remplacement d'une migration, mais un rollback en présence de données
        // Phase 15 déjà produites resterait de toute façon un cas dégradé, jamais le chemin
        // normal).
        $this->addSql('ALTER TABLE notifications ALTER COLUMN organization_id SET NOT NULL');

        $this->addSql('ALTER TABLE organizations DROP COLUMN suspended_at');

        $this->addSql('ALTER TABLE ai_call_log_entries DROP CONSTRAINT fk_ai_call_log_entries_organization_id');
        $this->addSql('DROP TABLE ai_call_log_entries');

        $this->addSql('ALTER TABLE platform_admin_mfa_challenges DROP CONSTRAINT fk_platform_admin_mfa_challenges_administrator_id');
        $this->addSql('DROP TABLE platform_admin_mfa_challenges');

        $this->addSql('DROP TABLE platform_administrators');
    }
}
