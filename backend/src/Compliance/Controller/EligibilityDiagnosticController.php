<?php

declare(strict_types=1);

namespace App\Compliance\Controller;

use App\Compliance\Repository\EligibilityDiagnosticRepository;
use App\Shared\Security\CurrentOrganizationResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /eligibility-diagnostics/current (docs/08-api-specification.md, section 29 ;
 * US-COMPLIANCE-001). Jamais calculé ici : toujours un effet de bord de
 * PATCH /organizations/current (App\Organization\Service\ConfigureOrganizationService) —
 * ce controller ne fait que lire le dernier diagnostic déjà calculé.
 *
 * 404 si aucun diagnostic n'existe encore (organisation pas encore configurée) : comportement
 * standard déjà défini par docs/08-api-specification.md section 42 pour toute ressource
 * inexistante, rien de spécifique à ajouter au contrat pour ce cas (voir plan Phase 3).
 */
final class EligibilityDiagnosticController
{
    public function __construct(
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly EligibilityDiagnosticRepository $eligibilityDiagnosticRepository,
    ) {
    }

    #[Route('/api/v1/eligibility-diagnostics/current', name: 'eligibility_diagnostics_current', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $diagnostic = $this->eligibilityDiagnosticRepository->findLatest($this->currentOrganizationResolver->getOrganizationId());

        if (null === $diagnostic) {
            throw new NotFoundHttpException("Aucun diagnostic d'éligibilité n'a encore été calculé pour cette organisation.");
        }

        return new JsonResponse([
            'data' => [
                'reception_obligation_date' => $diagnostic->getReceptionObligationDate()?->format('Y-m-d'),
                'emission_obligation_date' => $diagnostic->getEmissionObligationDate()?->format('Y-m-d'),
                'computed_at' => $diagnostic->getComputedAt()->format(\DateTimeInterface::ATOM),
                'explanation' => $diagnostic->getExplanation(),
            ],
        ]);
    }
}
