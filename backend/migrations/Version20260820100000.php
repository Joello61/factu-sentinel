<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 7 - Document Processing (docs/12-roadmap.md ; docs/07-data-model.md, sections
 * 13-14) : tables "documents" et "document_processing_records".
 *
 * Intégrité de "documents.current_processing_record_id -> document_processing_records" au
 * niveau base de données (plan Phase 7, correction demandée en revue) : une clé étrangère
 * composite garantit que le pointeur "tentative courante" appartient toujours au même
 * Document, jamais à celui d'un autre - impossible à exprimer via une contrainte simple
 * (FOREIGN KEY sur une seule colonne ne porte pas cette garantie). Nécessite une contrainte
 * UNIQUE (id, document_id) sur document_processing_records au préalable (PostgreSQL exige
 * qu'une clé étrangère composite référence des colonnes couvertes par une contrainte
 * unique/PK côté cible).
 */
final class Version20260820100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 7 - documents, document_processing_records (Document Processing).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE documents (id UUID NOT NULL, file_name VARCHAR(255) NOT NULL, file_format VARCHAR(255) DEFAULT NULL, file_size INT NOT NULL, checksum VARCHAR(64) NOT NULL, storage_reference VARCHAR(64) NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, organization_id UUID NOT NULL, invoice_id UUID NOT NULL, current_processing_record_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_documents_organization_id ON documents (organization_id)');
        $this->addSql('CREATE INDEX idx_documents_invoice_id ON documents (invoice_id)');

        $this->addSql('CREATE TABLE document_processing_records (id UUID NOT NULL, status VARCHAR(255) NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, failure_reason VARCHAR(255) DEFAULT NULL, extracted_data_summary JSON DEFAULT NULL, document_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_document_processing_records_document_id ON document_processing_records (document_id)');
        // Support de la clé étrangère composite ci-dessous (documents.current_processing_record_id).
        $this->addSql('CREATE UNIQUE INDEX uniq_document_processing_records_id_document_id ON document_processing_records (id, document_id)');

        $this->addSql('ALTER TABLE documents ADD CONSTRAINT fk_documents_organization_id FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT fk_documents_invoice_id FOREIGN KEY (invoice_id) REFERENCES invoices (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE document_processing_records ADD CONSTRAINT fk_document_processing_records_document_id FOREIGN KEY (document_id) REFERENCES documents (id) NOT DEFERRABLE');
        // Composite : garantit que current_processing_record_id référence toujours un
        // DocumentProcessingRecord dont document_id est bien CE Document (documents.id) -
        // ajoutée après les deux tables/l'index unique ci-dessus, jamais avant (ordre requis
        // par PostgreSQL).
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT fk_documents_current_processing_record FOREIGN KEY (current_processing_record_id, id) REFERENCES document_processing_records (id, document_id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documents DROP CONSTRAINT fk_documents_current_processing_record');
        $this->addSql('ALTER TABLE document_processing_records DROP CONSTRAINT fk_document_processing_records_document_id');
        $this->addSql('ALTER TABLE documents DROP CONSTRAINT fk_documents_invoice_id');
        $this->addSql('ALTER TABLE documents DROP CONSTRAINT fk_documents_organization_id');
        $this->addSql('DROP TABLE document_processing_records');
        $this->addSql('DROP TABLE documents');
    }
}
