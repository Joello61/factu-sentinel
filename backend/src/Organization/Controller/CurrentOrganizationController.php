<?php

declare(strict_types=1);

namespace App\Organization\Controller;

use App\Organization\Repository\FiscalContextRepository;
use App\Organization\Repository\OrganizationRepository;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use App\Shared\Security\CurrentOrganizationResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Premier endpoint authentifié permettant de vérifier la résolution du tenant courant
 * (pas "tenant-scoped" au sens strict : Organization est le tenant racine, elle n'a pas
 * de organization_id : voir plan Phase 2). L'identifiant vient exclusivement du jeton
 * (CurrentOrganizationResolver), jamais d'un paramètre d'URL : il n'existe donc
 * structurellement aucun moyen pour un client d'en demander une autre que la sienne.
 *
 * PATCH /organizations/current (configuration réelle) reste hors périmètre : Phase 3.
 */
final class CurrentOrganizationController
{
    public function __construct(
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly OrganizationRepository $organizationRepository,
        private readonly FiscalContextRepository $fiscalContextRepository,
    ) {
    }

    #[Route('/api/v1/organizations/current', name: 'organizations_current', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $organizationId = $this->currentOrganizationResolver->getOrganizationId();
        $organization = $this->organizationRepository->find($organizationId);

        if (null === $organization) {
            // Le jeton résout un organization_id qui n'existe plus en base : incohérence
            // interne, jamais un 404 ordinaire qui suggérerait une ressource d'un autre
            // tenant (10-security-privacy.md, section 17).
            throw new AuthenticatedIdentityWithoutOrganizationException('Resolved organization does not exist.');
        }

        $data = [
            'id' => $organization->getId()->toRfc4122(),
            'legal_name' => $organization->getLegalName(),
            'trade_name' => $organization->getTradeName(),
            'siren' => $organization->getSiren(),
            'siret' => $organization->getSiret(),
            'legal_form' => $organization->getLegalForm(),
            'country' => $organization->getCountry(),
            'configured' => $organization->isConfigured(),
            'created_at' => $organization->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        // fiscal_context absent tant que non configuré (Phase 3, docs/08-api-specification.md
        // section 24), plutôt que de forcer un objet à champs null qui laisserait croire à
        // une configuration partielle possible.
        $fiscalContext = $this->fiscalContextRepository->findCurrent($organizationId);
        if (null !== $fiscalContext) {
            $data['fiscal_context'] = [
                'vat_status' => $fiscalContext->getVatStatus()->value,
                'employees_count' => $fiscalContext->getEmployeesCount(),
                'annual_turnover' => $fiscalContext->getAnnualTurnover(),
                'annual_balance_sheet_total' => $fiscalContext->getAnnualBalanceSheetTotal(),
                'company_size_category' => $fiscalContext->getCompanySizeCategory()->value,
                'effective_from' => $fiscalContext->getEffectiveFrom()->format('Y-m-d'),
            ];
        }

        return new JsonResponse(['data' => $data]);
    }
}
