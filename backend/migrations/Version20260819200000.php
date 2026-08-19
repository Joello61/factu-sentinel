<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 5 - Compliance Engine (noyau) : ComplianceAnalysis/ComplianceFinding/
 * ContextSnapshot, idempotency_keys (Idempotency-Key, ../../CLAUDE.md racine section 11),
 * 3 nouvelles RegulatoryRule/RuleVersion (mention-siren-client, mention-categorie-operation,
 * format-facture-electronique) (docs/12-roadmap.md, Phase 5 ; docs/07-data-model.md, sections
 * 17-19 ; voir plan Phase 5).
 */
final class Version20260819200000 extends AbstractMigration
{
    private const string MENTION_SIREN_VERSION_ID = '00000000-0000-7000-8000-000000000003';
    private const string MENTION_CATEGORIE_VERSION_ID = '00000000-0000-7000-8000-000000000004';
    private const string FORMAT_VERSION_ID = '00000000-0000-7000-8000-000000000005';

    public function getDescription(): string
    {
        return 'ComplianceAnalysis, ComplianceFinding, ContextSnapshot, idempotency_keys, seed mention-siren-client/mention-categorie-operation/format-facture-electronique (Phase 5).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE compliance_analyses (id UUID NOT NULL, status VARCHAR(255) NOT NULL, triggered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, global_result VARCHAR(255) DEFAULT NULL, organization_id UUID NOT NULL, invoice_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_compliance_analyses_organization_id ON compliance_analyses (organization_id)');
        $this->addSql('CREATE INDEX idx_compliance_analyses_invoice_id ON compliance_analyses (invoice_id)');
        $this->addSql('CREATE TABLE context_snapshots (id UUID NOT NULL, customer_snapshot JSON NOT NULL, computed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, compliance_analysis_id UUID NOT NULL, fiscal_context_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_context_snapshots_compliance_analysis_id ON context_snapshots (compliance_analysis_id)');
        $this->addSql('CREATE INDEX idx_context_snapshots_fiscal_context_id ON context_snapshots (fiscal_context_id)');
        $this->addSql('CREATE TABLE compliance_findings (id UUID NOT NULL, result VARCHAR(255) NOT NULL, message TEXT NOT NULL, related_field VARCHAR(255) DEFAULT NULL, observed_value VARCHAR(500) DEFAULT NULL, correction_action TEXT DEFAULT NULL, compliance_analysis_id UUID NOT NULL, rule_version_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_compliance_findings_compliance_analysis_id ON compliance_findings (compliance_analysis_id)');
        $this->addSql('CREATE INDEX idx_compliance_findings_rule_version_id ON compliance_findings (rule_version_id)');
        $this->addSql('CREATE TABLE idempotency_keys (id UUID NOT NULL, organization_id UUID NOT NULL, idempotency_key VARCHAR(255) NOT NULL, response_status SMALLINT DEFAULT NULL, response_body JSON DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_idempotency_keys_organization_key ON idempotency_keys (organization_id, idempotency_key)');

        $this->addSql('ALTER TABLE compliance_analyses ADD CONSTRAINT FK_compliance_analyses_organization_id FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE compliance_analyses ADD CONSTRAINT FK_compliance_analyses_invoice_id FOREIGN KEY (invoice_id) REFERENCES invoices (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE context_snapshots ADD CONSTRAINT FK_context_snapshots_compliance_analysis_id FOREIGN KEY (compliance_analysis_id) REFERENCES compliance_analyses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE context_snapshots ADD CONSTRAINT FK_context_snapshots_fiscal_context_id FOREIGN KEY (fiscal_context_id) REFERENCES fiscal_contexts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE compliance_findings ADD CONSTRAINT FK_compliance_findings_compliance_analysis_id FOREIGN KEY (compliance_analysis_id) REFERENCES compliance_analyses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE compliance_findings ADD CONSTRAINT FK_compliance_findings_rule_version_id FOREIGN KEY (rule_version_id) REFERENCES rule_versions (id) NOT DEFERRABLE');

        $this->seedPhase5Rules();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE context_snapshots DROP CONSTRAINT FK_context_snapshots_compliance_analysis_id');
        $this->addSql('ALTER TABLE context_snapshots DROP CONSTRAINT FK_context_snapshots_fiscal_context_id');
        $this->addSql('ALTER TABLE compliance_findings DROP CONSTRAINT FK_compliance_findings_compliance_analysis_id');
        $this->addSql('ALTER TABLE compliance_findings DROP CONSTRAINT FK_compliance_findings_rule_version_id');
        $this->addSql('ALTER TABLE compliance_analyses DROP CONSTRAINT FK_compliance_analyses_organization_id');
        $this->addSql('ALTER TABLE compliance_analyses DROP CONSTRAINT FK_compliance_analyses_invoice_id');
        $this->addSql('DROP TABLE context_snapshots');
        $this->addSql('DROP TABLE compliance_findings');
        $this->addSql('DROP TABLE compliance_analyses');
        $this->addSql('DROP TABLE idempotency_keys');
        $this->addSql(sprintf(
            "DELETE FROM rule_versions WHERE id IN ('%s', '%s', '%s')",
            self::MENTION_SIREN_VERSION_ID,
            self::MENTION_CATEGORIE_VERSION_ID,
            self::FORMAT_VERSION_ID,
        ));
        $this->addSql("DELETE FROM regulatory_rules WHERE id IN ('mention-siren-client', 'mention-categorie-operation', 'format-facture-electronique')");
    }

    /**
     * Référentiel réglementaire versionné (docs/06-technical-architecture.md section 9).
     * Couvre 2 des 4 nouvelles mentions obligatoires de docs/02-regulatory-study.md section
     * 10 (SIREN client, catégorie d'opération) : option paiement TVA sur les débits et
     * adresse de livraison restent hors périmètre de cette phase (voir plan Phase 5).
     *
     * conditions.applicability est interprété par App\Compliance\Engine\Service\
     * ComplianceRuleEvaluator (customer_types, requires_non_exempt, sources) ;
     * conditions.outcomes[result] porte le message/correction_action figés par état.
     * severity porte l'état produit en cas de VIOLATED (docs/07-data-model.md, section 16).
     *
     * format-facture-electronique a confidence_level = MOYEN (docs/02-regulatory-study.md,
     * section 9 : correspondance formats/EN16931 confirmée à confiance Moyen, à revérifier
     * sur les sources DGFiP/AIFE avant activation réelle en Phase 7) : produit donc
     * systématiquement INCERTAIN_REGLEMENTAIRE si jamais son applicability devenait vraie
     * avant que la Phase 7 ne publie une nouvelle version à confiance Élevé -- mais son
     * applicability (sources: DOCUMENT_IMPORTE) ne peut de toute façon jamais être
     * satisfaite avant la Phase 7 (voir App\Compliance\Rules\RuleId).
     */
    private function seedPhase5Rules(): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO regulatory_rules (id, name, description, category, jurisdiction, status) VALUES
            ('mention-siren-client', 'Mention obligatoire du numéro SIREN du client',
             'Le numéro SIREN du client doit figurer sur les factures B2B domestiques depuis la réforme de la facturation électronique (docs/02-regulatory-study.md, section 10).',
             'MENTION_OBLIGATOIRE', 'FR', 'ACTIVE'),
            ('mention-categorie-operation', 'Mention obligatoire de la catégorie de l''opération',
             'La catégorie de l''opération (vente de bien, prestation de service, ou mixte) doit figurer sur les factures B2B domestiques depuis la réforme de la facturation électronique (docs/02-regulatory-study.md, section 10).',
             'MENTION_OBLIGATOIRE', 'FR', 'ACTIVE'),
            ('format-facture-electronique', 'Format électronique structuré et normé de la facture',
             'Une facture électronique conforme doit respecter un format structuré et normé (Factur-X, UBL, CII), pas un PDF simple ou un document non structuré (docs/02-regulatory-study.md, sections 8-9).',
             'FORMAT', 'FR', 'ACTIVE')
            SQL);

        $sirenConditions = json_encode([
            'applicability' => ['customer_types' => ['PROFESSIONNEL_FRANCAIS'], 'requires_non_exempt' => true],
            'outcomes' => [
                'CONFORME' => ['message' => "Le numéro SIREN de votre client est renseigné : cette mention obligatoire est respectée."],
                'NON_CONFORME' => [
                    'message' => "Le numéro SIREN de votre client professionnel français est absent de cette facture. Cette mention est obligatoire pour les factures B2B domestiques depuis la réforme de la facturation électronique.",
                    'correction_action' => "Renseignez le numéro SIREN de votre client dans sa fiche client, puis relancez l'analyse.",
                ],
                'NON_APPLICABLE' => ['message' => "Cette vérification concerne les mentions obligatoires de la facturation électronique B2B domestique : elle ne s'applique pas à ce client ou à cette opération (client particulier, étranger, ou opération exonérée de TVA)."],
            ],
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        $categorieConditions = json_encode([
            'applicability' => ['customer_types' => ['PROFESSIONNEL_FRANCAIS'], 'requires_non_exempt' => true],
            'outcomes' => [
                'CONFORME' => ['message' => "La catégorie de l'opération (vente de bien, prestation de service, ou mixte) est renseignée sur cette facture : cette mention obligatoire est respectée."],
                'NON_CONFORME' => [
                    'message' => "La catégorie de l'opération n'a pas pu être déterminée sur cette facture. Cette mention est obligatoire pour les factures B2B domestiques depuis la réforme de la facturation électronique.",
                    'correction_action' => "Précisez la catégorie de l'opération (vente de bien, prestation de service, ou mixte), puis relancez l'analyse.",
                ],
                'NON_APPLICABLE' => ['message' => "Cette vérification concerne les mentions obligatoires de la facturation électronique B2B domestique : elle ne s'applique pas à ce client ou à cette opération (client particulier, étranger, ou opération exonérée de TVA)."],
            ],
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        $formatConditions = json_encode([
            'applicability' => ['sources' => ['DOCUMENT_IMPORTE']],
            'outcomes' => [
                'NON_APPLICABLE' => ['message' => "Cette facture a été saisie manuellement dans FactuSentinel : la vérification du format électronique structuré (Factur-X, UBL, CII) s'applique aux documents importés, prise en charge à partir de l'import de documents (Phase 7 de la feuille de route produit)."],
            ],
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        $this->addSql(
            'INSERT INTO rule_versions (id, version_number, effective_from, effective_until, conditions, severity, source_reference, confidence_level, explanation_template, created_at, rule_id) VALUES (?, 1, ?, NULL, ?, ?, ?, ?, ?, NOW(), ?)',
            [
                self::MENTION_SIREN_VERSION_ID,
                '2026-01-01',
                $sirenConditions,
                'NON_CONFORME',
                'docs/02-regulatory-study.md, section 10',
                'ELEVE',
                "Le numéro SIREN du client est une mention obligatoire sur les factures B2B domestiques depuis la réforme de la facturation électronique (1er septembre 2026 pour les grandes entreprises et ETI, 1er septembre 2027 pour les PME, TPE et micro-entreprises).",
                'mention-siren-client',
            ],
        );

        $this->addSql(
            'INSERT INTO rule_versions (id, version_number, effective_from, effective_until, conditions, severity, source_reference, confidence_level, explanation_template, created_at, rule_id) VALUES (?, 1, ?, NULL, ?, ?, ?, ?, ?, NOW(), ?)',
            [
                self::MENTION_CATEGORIE_VERSION_ID,
                '2026-01-01',
                $categorieConditions,
                'NON_CONFORME',
                'docs/02-regulatory-study.md, section 10',
                'ELEVE',
                "La catégorie de l'opération (vente de bien, prestation de service, ou mixte) est une mention obligatoire sur les factures B2B domestiques depuis la réforme de la facturation électronique.",
                'mention-categorie-operation',
            ],
        );

        $this->addSql(
            'INSERT INTO rule_versions (id, version_number, effective_from, effective_until, conditions, severity, source_reference, confidence_level, explanation_template, created_at, rule_id) VALUES (?, 1, ?, NULL, ?, NULL, ?, ?, ?, NOW(), ?)',
            [
                self::FORMAT_VERSION_ID,
                '2026-01-01',
                $formatConditions,
                'docs/02-regulatory-study.md, sections 8-9',
                'MOYEN',
                "Une facture électronique doit respecter un format électronique structuré et normé (Factur-X, UBL ou CII, norme EN 16931) ; un PDF simple ou un document non structuré n'est pas conforme à cette exigence, même si les mentions obligatoires y figurent.",
                'format-facture-electronique',
            ],
        );
    }
}
