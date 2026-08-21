<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 14 (docs/12-roadmap.md) : App\Identity\Entity\Invitation, App\Notification\Entity\
 * Notification, et App\Identity\Entity\RefreshToken::$organizationId (jamais une preuve
 * d'appartenance à elle seule, voir App\Shared\Security\JwtOrganizationClaimListener).
 *
 * Élaguée manuellement du diff auto-généré par `doctrine:migrations:diff` : la commande
 * proposait aussi de renommer plusieurs index (nommage automatique du comparateur de schéma
 * vs. noms explicites choisis dans les migrations précédentes) et, plus grave, de
 * **supprimer sans jamais la recréer** `uniq_user_email` (index unique partiel
 * `WHERE deleted_at IS NULL`, Phase 13, US-SETTINGS-002) et `uniq_fiscal_contexts_current_per_organization`
 * (index unique partiel `WHERE effective_until IS NULL`, Phase 3) - le comparateur de schéma
 * de Doctrine ne reconnaît pas ces index partiels créés par SQL brut (limitation déjà
 * documentée sur ces deux migrations) et les traite comme un écart à corriger, alors qu'ils
 * sont corrects et volontaires. Aucune de ces suppressions n'a de rapport avec la Phase 14 :
 * retirées de cette migration plutôt qu'appliquées aveuglément.
 */
final class Version20260821133610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 14 : Invitation, Notification, RefreshToken.organization_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE invitations (
              id UUID NOT NULL,
              email VARCHAR(180) NOT NULL,
              role VARCHAR(255) NOT NULL,
              status VARCHAR(255) NOT NULL,
              token_selector VARCHAR(32) NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              organization_id UUID NOT NULL,
              invited_by UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232710AEE2CD3FDE ON invitations (token_selector)');
        $this->addSql('CREATE INDEX IDX_232710AE32C8A3DE ON invitations (organization_id)');
        $this->addSql('CREATE INDEX IDX_232710AE421FF255 ON invitations (invited_by)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              invitations
            ADD
              CONSTRAINT FK_232710AE32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              invitations
            ADD
              CONSTRAINT FK_232710AE421FF255 FOREIGN KEY (invited_by) REFERENCES users (id) NOT DEFERRABLE
        SQL);
        // Contrainte unique partielle (une seule Invitation "pending" par (organization_id,
        // email), plan Phase 14) - même patron que uniq_user_email (Phase 13) et
        // uniq_fiscal_contexts_current_per_organization (Phase 3) : les attributs Doctrine ORM
        // ne supportent pas les index partiels, créée en SQL brut.
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_invitations_pending_per_organization_email ON invitations (organization_id, email)
            WHERE
              (status = 'pending')
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE notifications (
              id UUID NOT NULL,
              notification_type VARCHAR(255) NOT NULL,
              sender_type VARCHAR(255) NOT NULL,
              target_type VARCHAR(255) NOT NULL,
              message VARCHAR(1000) NOT NULL,
              channel VARCHAR(255) NOT NULL,
              status VARCHAR(255) NOT NULL,
              source_diagnostic_id UUID DEFAULT NULL,
              scheduled_for TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              organization_id UUID NOT NULL,
              recipient_user_id UUID NOT NULL,
              sender_id UUID DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_6000B0D332C8A3DE ON notifications (organization_id)');
        $this->addSql('CREATE INDEX IDX_6000B0D3B15EFB97 ON notifications (recipient_user_id)');
        $this->addSql('CREATE INDEX IDX_6000B0D3F624B39D ON notifications (sender_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              notifications
            ADD
              CONSTRAINT FK_6000B0D332C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              notifications
            ADD
              CONSTRAINT FK_6000B0D3B15EFB97 FOREIGN KEY (recipient_user_id) REFERENCES users (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              notifications
            ADD
              CONSTRAINT FK_6000B0D3F624B39D FOREIGN KEY (sender_id) REFERENCES users (id) NOT DEFERRABLE
        SQL);

        $this->addSql('ALTER TABLE refresh_tokens ADD organization_id UUID DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitations DROP CONSTRAINT FK_232710AE32C8A3DE');
        $this->addSql('ALTER TABLE invitations DROP CONSTRAINT FK_232710AE421FF255');
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_6000B0D332C8A3DE');
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_6000B0D3B15EFB97');
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_6000B0D3F624B39D');
        $this->addSql('DROP TABLE invitations');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('ALTER TABLE refresh_tokens DROP organization_id');
    }
}
