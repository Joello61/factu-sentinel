<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 4 - Customers & Invoicing (saisie manuelle) : Customer, Invoice, InvoiceLine
 * (docs/12-roadmap.md, Phase 4 ; docs/07-data-model.md, sections 8, 10-11 ; voir plan Phase
 * 4). Générée par doctrine:migrations:diff puis corrigée à la main (voir commentaire dans
 * up() ci-dessous).
 */
final class Version20260819172937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer, Invoice, InvoiceLine (Phase 4).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE customers (
              id UUID NOT NULL,
              customer_type VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              siren VARCHAR(9) DEFAULT NULL,
              vat_number VARCHAR(32) DEFAULT NULL,
              country VARCHAR(2) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              organization_id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_customers_organization_id ON customers (organization_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE invoice_lines (
              id UUID NOT NULL,
              description VARCHAR(500) NOT NULL,
              quantity NUMERIC(14, 3) NOT NULL,
              unit_price_ht NUMERIC(14, 2) NOT NULL,
              vat_rate NUMERIC(5, 4) NOT NULL,
              line_amount_ht NUMERIC(14, 2) NOT NULL,
              line_amount_vat NUMERIC(14, 2) NOT NULL,
              line_amount_ttc NUMERIC(14, 2) NOT NULL,
              invoice_id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_invoice_lines_invoice_id ON invoice_lines (invoice_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE invoices (
              id UUID NOT NULL,
              invoice_number VARCHAR(100) DEFAULT NULL,
              issue_date DATE NOT NULL,
              operation_type VARCHAR(255) NOT NULL,
              currency VARCHAR(3) NOT NULL,
              total_amount_ht NUMERIC(14, 2) NOT NULL,
              total_amount_ttc NUMERIC(14, 2) NOT NULL,
              vat_exemption_reason VARCHAR(255) DEFAULT NULL,
              status VARCHAR(255) NOT NULL,
              source VARCHAR(255) NOT NULL,
              version INT DEFAULT 1 NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              organization_id UUID NOT NULL,
              customer_id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_6A2F2F959395C3F3 ON invoices (customer_id)');
        $this->addSql('CREATE INDEX idx_invoices_organization_id ON invoices (organization_id)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_invoices_organization_invoice_number ON invoices (organization_id, invoice_number)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              customers
            ADD
              CONSTRAINT FK_62534E2132C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              invoice_lines
            ADD
              CONSTRAINT FK_72DBDC232989F1FD FOREIGN KEY (invoice_id) REFERENCES invoices (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              invoices
            ADD
              CONSTRAINT FK_6A2F2F9532C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              invoices
            ADD
              CONSTRAINT FK_6A2F2F959395C3F3 FOREIGN KEY (customer_id) REFERENCES customers (id) NOT DEFERRABLE
        SQL);
        // La ligne "DROP INDEX uniq_fiscal_contexts_current_per_organization" générée par le
        // diff est un faux positif : cet index partiel (WHERE effective_until IS NULL) est créé
        // à la main en Phase 3 (voir Version20260819121231::up()) car le driver attribute de
        // Doctrine ORM ne représente pas nativement les index partiels dans le mapping
        // (App\Organization\Entity\FiscalContext, commentaire de classe) - le diff le croit donc
        // orphelin à chaque régénération. Volontairement omise ici : la retirer casserait
        // l'invariant "au plus une ligne courante par organisation" posé en Phase 3.
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customers DROP CONSTRAINT FK_62534E2132C8A3DE');
        $this->addSql('ALTER TABLE invoice_lines DROP CONSTRAINT FK_72DBDC232989F1FD');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT FK_6A2F2F9532C8A3DE');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT FK_6A2F2F959395C3F3');
        $this->addSql('DROP TABLE customers');
        $this->addSql('DROP TABLE invoice_lines');
        $this->addSql('DROP TABLE invoices');
    }
}
