<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Http;

use App\Compliance\Engine\Entity\ComplianceAnalysis;
use App\Compliance\Engine\Entity\ComplianceFinding;

/**
 * docs/08-api-specification.md, section 30/46 : un global_result NON_CONFORME passe
 * toujours par une réponse 200 {data:...}, jamais par le contrat d'erreur.
 */
final class ComplianceAnalysisView
{
    /**
     * @param list<ComplianceFinding> $findings
     *
     * @return array<string, mixed>
     */
    public static function fromEntity(ComplianceAnalysis $analysis, array $findings): array
    {
        return [
            'id' => $analysis->getId()->toRfc4122(),
            'invoice_id' => $analysis->getInvoice()->getId()->toRfc4122(),
            'status' => $analysis->getStatus()->value,
            'global_result' => $analysis->getGlobalResult()?->value,
            'triggered_at' => $analysis->getTriggeredAt()->format(\DateTimeInterface::ATOM),
            'completed_at' => $analysis->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'findings' => array_map(ComplianceFindingView::fromEntity(...), $findings),
        ];
    }
}
