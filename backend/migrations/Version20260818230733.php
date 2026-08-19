<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 1 - Technical Foundation : premières tables (docs/12-roadmap.md, Phase 1 ;
 * docs/07-data-model.md, sections 5-6).
 */
final class Version20260818230733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Premières tables : organizations, users, memberships (Phase 1 - Technical Foundation).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE memberships (id UUID NOT NULL, role VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_865A4776A76ED395 ON memberships (user_id)');
        $this->addSql('CREATE INDEX IDX_865A477632C8A3DE ON memberships (organization_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_membership_user_organization ON memberships (user_id, organization_id)');
        $this->addSql('CREATE TABLE organizations (id UUID NOT NULL, legal_name VARCHAR(255) NOT NULL, trade_name VARCHAR(255) DEFAULT NULL, siren VARCHAR(9) NOT NULL, siret VARCHAR(14) DEFAULT NULL, legal_form VARCHAR(100) DEFAULT NULL, country VARCHAR(2) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(180) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email)');
        $this->addSql('ALTER TABLE memberships ADD CONSTRAINT FK_865A4776A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE memberships ADD CONSTRAINT FK_865A477632C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE memberships DROP CONSTRAINT FK_865A4776A76ED395');
        $this->addSql('ALTER TABLE memberships DROP CONSTRAINT FK_865A477632C8A3DE');
        $this->addSql('DROP TABLE memberships');
        $this->addSql('DROP TABLE organizations');
        $this->addSql('DROP TABLE users');
    }
}
