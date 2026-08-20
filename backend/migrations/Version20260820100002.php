<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 7 - RuleVersion v2 de format-facture-electronique (docs/12-roadmap.md ;
 * docs/06-technical-architecture.md, ADR-003 : immutabilité - jamais de UPDATE sur la v1
 * seedée en Phase 5, Version20260819200000).
 *
 * Vérification réglementaire effectuée avant cette migration (plan Phase 7, instruction
 * explicite) : la page officielle DGFiP impots.gouv.fr/factures-norme-afnor (modifiée le
 * 02/07/2026) référence les normes AFNOR XP Z12-012/013/014 couvrant Factur-X/UBL/CII,
 * cohérent avec docs/02-regulatory-study.md section 8 (citation economie.gouv.fr, confiance
 * déjà Élevée : un PDF simple/papier scanné/email "ne sera plus conforme") et section 9/23
 * (liste des 3 formats déjà actée "Résolu"). confidence_level passe donc de MOYEN (v1) à
 * ÉLEVÉ (v2) - docs/02-regulatory-study.md section 23 mis à jour en conséquence dans la même
 * tâche.
 */
final class Version20260820100002 extends AbstractMigration
{
    private const string FORMAT_VERSION_2_ID = '00000000-0000-7000-8000-000000000006';
    private const string FORMAT_VERSION_1_ID = '00000000-0000-7000-8000-000000000005';

    public function getDescription(): string
    {
        return 'Phase 7 - RuleVersion v2 format-facture-electronique (confidence_level MOYEN -> ELEVE).';
    }

    public function up(Schema $schema): void
    {
        // Ferme la v1 : effective_until = même date que effective_from de la v2, jamais la
        // veille - App\Compliance\Rules\Repository\RuleVersionRepository::findActive() teste
        // "effectiveUntil > :at" (borne exclusive) et "effectiveFrom <= :at" (borne
        // inclusive) : une valeur "veille" créerait un jour sans aucune version active
        // (violerait l'absence de chevauchement/gap exigée par docs/07-data-model.md,
        // section 37 - repéré et corrigé pendant l'implémentation). Jamais un UPDATE du
        // contenu de la v1, uniquement sa fin de validité (ADR-003).
        $this->addSql(
            "UPDATE rule_versions SET effective_until = '2026-08-20' WHERE id = ?",
            [self::FORMAT_VERSION_1_ID],
        );

        $conditions = json_encode([
            // "requires_document" (pas "sources": ["DOCUMENT_IMPORTE"]) : sous la décision
            // produit retenue en Phase 7 (plan Phase 7, décision 1, corrigée), une Invoice
            // reste toujours InvoiceSource::SAISIE_MANUELLE même lorsqu'un document lui est
            // rattaché après coup - "sources" ne serait donc jamais satisfait par aucun
            // chemin de code réel. La bonne condition est la présence d'un Document
            // (App\Compliance\Engine\Service\ComplianceRuleEvaluator::isApplicable()) -
            // corrigé avant tout usage réel de cette RuleVersion.
            'applicability' => ['requires_document' => true],
            'outcomes' => [
                'CONFORME' => [
                    'message' => "Ce document respecte un format électronique structuré et normé (Factur-X) : cette exigence de la réforme de la facturation électronique est respectée.",
                ],
                'NON_CONFORME' => [
                    'message' => "Ce document n'est pas une facture électronique conforme au sens de la réforme, même s'il contient par ailleurs toutes les mentions obligatoires attendues. Une facture électronique conforme doit respecter un format structuré et normé (Factur-X, UBL ou CII) - un PDF simple, un document scanné ou envoyé par email ne suffit pas.",
                    'correction_action' => "Importez ou générez une version structurée de cette facture (Factur-X en priorité), ou utilisez votre plateforme agréée pour la produire dans un format conforme.",
                ],
                'A_VERIFIER' => [
                    'message' => "Le format électronique de ce document n'a pas encore pu être déterminé (traitement en cours, ou document source indisponible). Cette vérification sera automatiquement complétée dès que le traitement du document sera terminé.",
                ],
                'NON_APPLICABLE' => ['message' => "Aucun document n'est encore rattaché à cette facture : la vérification du format électronique structuré (Factur-X, UBL, CII) s'applique aux documents importés, une fois qu'au moins un document est associé à la facture."],
            ],
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        $this->addSql(
            'INSERT INTO rule_versions (id, version_number, effective_from, effective_until, conditions, severity, source_reference, confidence_level, explanation_template, created_at, rule_id) VALUES (?, 2, ?, NULL, ?, ?, ?, ?, ?, NOW(), ?)',
            [
                self::FORMAT_VERSION_2_ID,
                '2026-08-20',
                $conditions,
                'NON_CONFORME',
                'docs/02-regulatory-study.md, sections 8-9, 23 (vérification DGFiP complémentaire, Phase 7)',
                'ELEVE',
                "Une facture électronique doit respecter un format électronique structuré et normé (Factur-X, UBL ou CII, norme EN 16931) ; un PDF simple ou un document non structuré n'est pas conforme à cette exigence, même si les mentions obligatoires y figurent.",
                'format-facture-electronique',
            ],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM rule_versions WHERE id = ?', [self::FORMAT_VERSION_2_ID]);
        $this->addSql(
            'UPDATE rule_versions SET effective_until = NULL WHERE id = ?',
            [self::FORMAT_VERSION_1_ID],
        );
    }
}
