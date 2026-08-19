<?php

declare(strict_types=1);

namespace App\Organization\Controller;

use App\Organization\Repository\OrganizationRepository;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use App\Shared\Security\CurrentOrganizationResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Premier endpoint authentifié permettant de vérifier la résolution du tenant courant
 * (pas "tenant-scoped" au sens strict : Organization est le tenant racine, elle n'a pas
 * de organization_id — voir plan Phase 2). L'identifiant vient exclusivement du jeton
 * (CurrentOrganizationResolver), jamais d'un paramètre d'URL : il n'existe donc
 * structurellement aucun moyen pour un client d'en demander une autre que la sienne.
 *
 * PATCH /organizations/current (configuration réelle) reste hors périmètre — Phase 3.
 */
final class CurrentOrganizationController
{
    public function __construct(
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly OrganizationRepository $organizationRepository,
    ) {
    }

    #[Route('/api/v1/organizations/current', name: 'organizations_current', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $organization = $this->organizationRepository->find($this->currentOrganizationResolver->getOrganizationId());

        if (null === $organization) {
            // Le jeton résout un organization_id qui n'existe plus en base : incohérence
            // interne, jamais un 404 ordinaire qui suggérerait une ressource d'un autre
            // tenant (10-security-privacy.md, section 17).
            throw new AuthenticatedIdentityWithoutOrganizationException('Resolved organization does not exist.');
        }

        return new JsonResponse([
            'data' => [
                'id' => $organization->getId()->toRfc4122(),
                'legal_name' => $organization->getLegalName(),
                'trade_name' => $organization->getTradeName(),
                'siren' => $organization->getSiren(),
                'siret' => $organization->getSiret(),
                'legal_form' => $organization->getLegalForm(),
                'country' => $organization->getCountry(),
                'configured' => $organization->isConfigured(),
                'created_at' => $organization->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }
}
