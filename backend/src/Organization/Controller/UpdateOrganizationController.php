<?php

declare(strict_types=1);

namespace App\Organization\Controller;

use App\Organization\Repository\OrganizationRepository;
use App\Organization\Service\ConfigureOrganizationService;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use App\Shared\Security\CurrentOrganizationResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PATCH /organizations/current (docs/08-api-specification.md, section 24, payload
 * corrigé : voir plan Phase 3, gap 1 : trois valeurs brutes en entrée, jamais
 * company_size_category directement). Controller volontairement mince
 * (backend/CLAUDE.md, section 3) : délègue toute la logique à ConfigureOrganizationService.
 *
 * Ne passe pas par #[MapRequestPayload] : la sémantique de fusion partielle de cet endpoint
 * exige de distinguer un champ absent d'un champ explicitement null, ce que le mapping
 * automatique de Symfony ne permet pas nativement (voir App\Organization\Http\MergedFiscalContextInput).
 */
final class UpdateOrganizationController
{
    public function __construct(
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly OrganizationRepository $organizationRepository,
        private readonly ConfigureOrganizationService $configureOrganizationService,
    ) {
    }

    #[Route('/api/v1/organizations/current', name: 'organizations_current_update', methods: ['PATCH'])]
    public function __invoke(Request $request): JsonResponse
    {
        $organization = $this->organizationRepository->find($this->currentOrganizationResolver->getOrganizationId());

        if (null === $organization) {
            throw new AuthenticatedIdentityWithoutOrganizationException('Resolved organization does not exist.');
        }

        $payload = $request->toArray();

        $result = $this->configureOrganizationService->configure($organization, $payload);

        $data = [
            'id' => $result->organization->getId()->toRfc4122(),
            'legal_name' => $result->organization->getLegalName(),
            'trade_name' => $result->organization->getTradeName(),
            'siren' => $result->organization->getSiren(),
            'siret' => $result->organization->getSiret(),
            'legal_form' => $result->organization->getLegalForm(),
            'country' => $result->organization->getCountry(),
            'configured' => $result->organization->isConfigured(),
            'created_at' => $result->organization->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if (null !== $result->fiscalContext) {
            $data['fiscal_context'] = [
                'vat_status' => $result->fiscalContext->getVatStatus()->value,
                'employees_count' => $result->fiscalContext->getEmployeesCount(),
                'annual_turnover' => $result->fiscalContext->getAnnualTurnover(),
                'annual_balance_sheet_total' => $result->fiscalContext->getAnnualBalanceSheetTotal(),
                'company_size_category' => $result->fiscalContext->getCompanySizeCategory()->value,
                'effective_from' => $result->fiscalContext->getEffectiveFrom()->format('Y-m-d'),
            ];
        }

        if (null !== $result->diagnostic) {
            $data['eligibility_diagnostic'] = [
                'reception_obligation_date' => $result->diagnostic->getReceptionObligationDate()?->format('Y-m-d'),
                'emission_obligation_date' => $result->diagnostic->getEmissionObligationDate()?->format('Y-m-d'),
                'computed_at' => $result->diagnostic->getComputedAt()->format(\DateTimeInterface::ATOM),
                'explanation' => $result->diagnostic->getExplanation(),
            ];
        }

        return new JsonResponse(['data' => $data]);
    }
}
