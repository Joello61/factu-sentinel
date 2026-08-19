<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 3 - Organization & Fiscal Context : FiscalContext historisé, référentiel
 * réglementaire minimal (RegulatoryRule/RuleVersion, deux règles d'éligibilité seedées),
 * EligibilityDiagnostic, AuditLogEntry (docs/12-roadmap.md, Phase 3 ; voir plan).
 */
final class Version20260819121231 extends AbstractMigration
{
    private const string FRANCHISE_RULE_VERSION_ID = '00000000-0000-7000-8000-000000000001';
    private const string CALENDAR_RULE_VERSION_ID = '00000000-0000-7000-8000-000000000002';

    public function getDescription(): string
    {
        return 'FiscalContext, RegulatoryRule/RuleVersion (seed eligibilite-*), EligibilityDiagnostic, AuditLogEntry (Phase 3).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_log_entries (id UUID NOT NULL, organization_id UUID DEFAULT NULL, actor_type VARCHAR(255) NOT NULL, actor_id UUID DEFAULT NULL, event_type VARCHAR(255) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id VARCHAR(100) NOT NULL, previous_state JSON DEFAULT NULL, new_state JSON DEFAULT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_audit_log_entries_organization_id ON audit_log_entries (organization_id)');
        $this->addSql('CREATE TABLE eligibility_diagnostics (id UUID NOT NULL, reception_obligation_date DATE DEFAULT NULL, emission_obligation_date DATE DEFAULT NULL, explanation TEXT NOT NULL, computed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, organization_id UUID NOT NULL, fiscal_context_id UUID NOT NULL, franchise_rule_version_id UUID NOT NULL, calendar_rule_version_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D524076683682ACC ON eligibility_diagnostics (fiscal_context_id)');
        $this->addSql('CREATE INDEX IDX_D5240766B89D0C42 ON eligibility_diagnostics (franchise_rule_version_id)');
        $this->addSql('CREATE INDEX IDX_D52407661BD47875 ON eligibility_diagnostics (calendar_rule_version_id)');
        $this->addSql('CREATE INDEX idx_eligibility_diagnostics_organization_id ON eligibility_diagnostics (organization_id)');
        $this->addSql('CREATE TABLE fiscal_contexts (id UUID NOT NULL, vat_status VARCHAR(255) NOT NULL, employees_count INT NOT NULL, annual_turnover NUMERIC(16, 2) NOT NULL, annual_balance_sheet_total NUMERIC(16, 2) NOT NULL, company_size_category VARCHAR(255) NOT NULL, effective_from DATE NOT NULL, effective_until DATE DEFAULT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_fiscal_contexts_organization_id ON fiscal_contexts (organization_id)');
        // Index partiel (docs/07-data-model.md section 37) : au plus une ligne "courante" par
        // organisation. Ne garantit pas à lui seul l'absence de chevauchement historique — voir
        // App\Organization\Entity\FiscalContext et le plan Phase 3, point 6 de la revue.
        $this->addSql('CREATE UNIQUE INDEX uniq_fiscal_contexts_current_per_organization ON fiscal_contexts (organization_id) WHERE (effective_until IS NULL)');
        $this->addSql('CREATE TABLE regulatory_rules (id VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT NOT NULL, category VARCHAR(255) NOT NULL, jurisdiction VARCHAR(2) NOT NULL, status VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE rule_versions (id UUID NOT NULL, version_number INT NOT NULL, effective_from DATE NOT NULL, effective_until DATE DEFAULT NULL, conditions JSON NOT NULL, severity VARCHAR(50) DEFAULT NULL, source_reference VARCHAR(255) NOT NULL, confidence_level VARCHAR(255) NOT NULL, explanation_template TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, rule_id VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_rule_versions_rule_id ON rule_versions (rule_id)');
        $this->addSql('ALTER TABLE eligibility_diagnostics ADD CONSTRAINT FK_D524076632C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE eligibility_diagnostics ADD CONSTRAINT FK_D524076683682ACC FOREIGN KEY (fiscal_context_id) REFERENCES fiscal_contexts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE eligibility_diagnostics ADD CONSTRAINT FK_D5240766B89D0C42 FOREIGN KEY (franchise_rule_version_id) REFERENCES rule_versions (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE eligibility_diagnostics ADD CONSTRAINT FK_D52407661BD47875 FOREIGN KEY (calendar_rule_version_id) REFERENCES rule_versions (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE fiscal_contexts ADD CONSTRAINT FK_F3777E5032C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rule_versions ADD CONSTRAINT FK_D520A37E744E0351 FOREIGN KEY (rule_id) REFERENCES regulatory_rules (id) NOT DEFERRABLE');

        $this->seedEligibilityRules();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE eligibility_diagnostics DROP CONSTRAINT FK_D524076632C8A3DE');
        $this->addSql('ALTER TABLE eligibility_diagnostics DROP CONSTRAINT FK_D524076683682ACC');
        $this->addSql('ALTER TABLE eligibility_diagnostics DROP CONSTRAINT FK_D5240766B89D0C42');
        $this->addSql('ALTER TABLE eligibility_diagnostics DROP CONSTRAINT FK_D52407661BD47875');
        $this->addSql('ALTER TABLE fiscal_contexts DROP CONSTRAINT FK_F3777E5032C8A3DE');
        $this->addSql('ALTER TABLE rule_versions DROP CONSTRAINT FK_D520A37E744E0351');
        $this->addSql('DROP TABLE audit_log_entries');
        $this->addSql('DROP TABLE eligibility_diagnostics');
        $this->addSql('DROP TABLE fiscal_contexts');
        $this->addSql('DROP TABLE regulatory_rules');
        $this->addSql('DROP TABLE rule_versions');
    }

    /**
     * Référentiel réglementaire versionné (docs/06-technical-architecture.md section 9 ;
     * docs plan Phase 3, décision 1) : les deux seuils/dates du diagnostic d'éligibilité ne
     * sont jamais codés en dur dans le calculateur applicatif, ils vivent ici, comme donnée.
     *
     * Seuils PME/ETI de "eligibilite-calendrier-taille" : sourcés DGFiP (calendrier et
     * catégories d'entreprises) et INSEE (définitions des catégories d'entreprises) — voir
     * la mise à jour correspondante de docs/02-regulatory-study.md sections 5-6, réalisée
     * dans le cadre de cette même tâche. Seul le seuil PME est encodé (250 salariés /
     * 50 M€ CA / 43 M€ bilan, OR entre les deux critères monétaires) : le calendrier de la
     * réforme regroupe ETI et grande entreprise sous une même date d'émission, ce produit
     * n'a donc jamais besoin de distinguer les deux (voir CompanySizeCategoryResolver).
     */
    private function seedEligibilityRules(): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO regulatory_rules (id, name, description, category, jurisdiction, status) VALUES
            ('eligibilite-franchise-en-base', 'Assujettissement même en franchise en base de TVA',
             'Une entreprise en franchise en base de TVA reste assujettie et donc concernée par la réforme de la facturation électronique, en réception comme en émission (BR-ELIGIBILITY-001).',
             'ELIGIBILITE', 'FR', 'ACTIVE'),
            ('eligibilite-calendrier-taille', 'Calendrier d''émission différencié selon la taille de l''entreprise',
             'La date d''obligation d''émission de factures électroniques conformes dépend de la taille de l''entreprise : 1er septembre 2026 pour les grandes entreprises et ETI, 1er septembre 2027 pour les PME, TPE et micro-entreprises.',
             'ELIGIBILITE', 'FR', 'ACTIVE')
            SQL);

        $franchiseConditions = json_encode([
            'in_scope_vat_statuses' => ['ASSUJETTI_REDEVABLE', 'ASSUJETTI_FRANCHISE_EN_BASE'],
            'out_of_scope_vat_statuses' => ['NON_ASSUJETTI'],
            'reception_obligation_date' => '2026-09-01',
            'out_of_scope_explanation' => "Votre entreprise n'est pas assujettie à la TVA : en l'état des informations renseignées, elle ne relève pas du périmètre de la réforme de la facturation électronique.",
            'franchise_en_base_note' => "Vous bénéficiez de la franchise en base de TVA : vous n'êtes pas redevable de la TVA, mais vous restez assujetti et donc concerné par la réforme.",
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        $calendarConditions = json_encode([
            'pme_threshold' => [
                'employees_count_max_exclusive' => 250,
                'annual_turnover_max' => '50000000.00',
                'annual_balance_sheet_total_max' => '43000000.00',
            ],
            'emission_date_pme_tpe_micro' => '2027-09-01',
            'emission_date_grande_entreprise_eti' => '2026-09-01',
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        $this->addSql(
            'INSERT INTO rule_versions (id, version_number, effective_from, effective_until, conditions, severity, source_reference, confidence_level, explanation_template, created_at, rule_id) VALUES (?, 1, ?, NULL, ?, NULL, ?, ?, ?, NOW(), ?)',
            [
                self::FRANCHISE_RULE_VERSION_ID,
                '2026-01-01',
                $franchiseConditions,
                'docs/02-regulatory-study.md, sections 5-6',
                'ELEVE',
                "Votre entreprise est assujettie à la TVA : elle est donc concernée par la réforme de la facturation électronique, avec une obligation de réception des factures électroniques à partir du 1er septembre 2026.",
                'eligibilite-franchise-en-base',
            ],
        );

        $this->addSql(
            'INSERT INTO rule_versions (id, version_number, effective_from, effective_until, conditions, severity, source_reference, confidence_level, explanation_template, created_at, rule_id) VALUES (?, 1, ?, NULL, ?, NULL, ?, ?, ?, NOW(), ?)',
            [
                self::CALENDAR_RULE_VERSION_ID,
                '2026-01-01',
                $calendarConditions,
                'docs/02-regulatory-study.md, section 5 ; seuils PME/ETI : DGFiP (calendrier et catégories d\'entreprises), INSEE (définitions des catégories d\'entreprises)',
                'ELEVE',
                "La date à partir de laquelle vous devez émettre des factures électroniques conformes dépend de la taille de votre entreprise, appréciée sur le dernier exercice clos avant le 1er janvier 2025 (ou le premier exercice clos à compter de cette date).",
                'eligibilite-calendrier-taille',
            ],
        );
    }
}
