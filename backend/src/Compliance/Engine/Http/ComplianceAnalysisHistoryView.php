<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Http;

use App\Compliance\Engine\Entity\ComplianceAnalysis;

/**
 * GET /compliance-analyses (docs/08-api-specification.md, section 29 bis - historique
 * organisation-wide, US-HISTORY-001). Même forme allégée que la liste par facture
 * (App\Compliance\Engine\Controller\ListComplianceAnalysesController), avec en plus
 * invoice_id/invoice_number : indispensable ici, cette liste n'étant pas déjà scopée à une
 * facture connue de l'appelant.
 */
final class ComplianceAnalysisHistoryView
{
    /**
     * @return array<string, mixed>
     */
    public static function fromEntity(ComplianceAnalysis $analysis): array
    {
        return [
            'id' => $analysis->getId()->toRfc4122(),
            'invoice_id' => $analysis->getInvoice()->getId()->toRfc4122(),
            'invoice_number' => $analysis->getInvoice()->getInvoiceNumber(),
            'status' => $analysis->getStatus()->value,
            'global_result' => $analysis->getGlobalResult()?->value,
            'triggered_at' => $analysis->getTriggeredAt()->format(\DateTimeInterface::ATOM),
            'completed_at' => $analysis->getCompletedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
