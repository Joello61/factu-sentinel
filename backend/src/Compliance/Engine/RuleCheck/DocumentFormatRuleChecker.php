<?php

declare(strict_types=1);

namespace App\Compliance\Engine\RuleCheck;

use App\Compliance\Engine\RuleEvaluationContext;
use App\Document\Enum\DocumentFileFormat;

/**
 * Règle format-facture-electronique (docs/02-regulatory-study.md, sections 8-9 ; plan Phase
 * 7 ; US-COMPLIANCE-005). N'est invoqué par App\Compliance\Engine\Service\
 * ComplianceRuleEvaluator que lorsque l'applicabilité (sources: DOCUMENT_IMPORTE) et la
 * confiance élevée de la RuleVersion sont déjà confirmées - ce checker ne réévalue ni l'une
 * ni l'autre.
 *
 * Ne lit que Document::getFileFormat() (champ structuré, déjà classifié par
 * App\Document\MessageHandler\ExtractDocumentContentHandler) - jamais
 * DocumentProcessingRecord::extractedDataSummary (invariant central du plan Phase 7).
 */
final class DocumentFormatRuleChecker implements RuleCheckerInterface
{
    public function check(RuleEvaluationContext $context): RuleCheckResult
    {
        if (null === $context->document) {
            // Facture DOCUMENT_IMPORTE mais Document introuvable/supprimé entre-temps
            // (US-DOCUMENT-002) : donnée manquante empêchant la vérification, jamais
            // NON_CONFORME par défaut (BR-COMPLIANCE-003).
            return new RuleCheckResult(RuleCheckOutcome::DATA_MISSING, 'document.file_format', null);
        }

        $fileFormat = $context->document->getFileFormat();

        if (null === $fileFormat) {
            // Classification pas encore déterminée (traitement asynchrone en cours) ou
            // définitivement en échec technique (ex. MUSTANG_UNAVAILABLE) : donnée
            // manquante, jamais NON_CONFORME par défaut (BR-COMPLIANCE-003) - distinct du
            // cas ci-dessous, où le format EST déterminé et n'est simplement pas FACTURX.
            return new RuleCheckResult(RuleCheckOutcome::DATA_MISSING, 'document.file_format', null);
        }

        return new RuleCheckResult(
            DocumentFileFormat::FACTURX === $fileFormat ? RuleCheckOutcome::SATISFIED : RuleCheckOutcome::VIOLATED,
            'document.file_format',
            $fileFormat->value,
        );
    }
}
