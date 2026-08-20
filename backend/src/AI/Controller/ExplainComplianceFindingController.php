<?php

declare(strict_types=1);

namespace App\AI\Controller;

use App\AI\Service\ExplainComplianceFindingService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /compliance-findings/{id}/explanations (docs/08-api-specification.md, section 35 ;
 * US-AI-001). Aucun corps de requête attendu - le contexte est résolu côté serveur à partir
 * du finding déjà existant (contrainte du contrat elle-même, section 35 : "jamais fourni
 * librement par le client").
 */
final class ExplainComplianceFindingController
{
    public function __construct(
        private readonly ExplainComplianceFindingService $explainComplianceFindingService,
    ) {
    }

    #[Route('/api/v1/compliance-findings/{id}/explanations', name: 'compliance_finding_explanations', methods: ['POST'])]
    public function __invoke(string $id): JsonResponse
    {
        $result = $this->explainComplianceFindingService->explain($id);

        return new JsonResponse($result['body'], $result['status']);
    }
}
