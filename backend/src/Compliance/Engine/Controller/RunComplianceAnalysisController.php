<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Controller;

use App\Compliance\Engine\Service\RunComplianceAnalysisService;
use App\Invoicing\Repository\InvoiceRepository;
use App\Organization\Repository\OrganizationRepository;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use App\Shared\Security\CurrentOrganizationResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * POST /invoices/{id}/compliance-analyses (docs/08-api-specification.md, section 29-30 ;
 * US-COMPLIANCE-002). Toujours 200 OK en Phase 5 (saisie manuelle = toujours synchrone) :
 * le chemin 202/status_url documenté pour l'analyse asynchrone reste hors périmètre tant
 * que Document/Phase 7 n'existe pas.
 *
 * Idempotency-Key requis (../../CLAUDE.md racine, section 11) : absent -> 400, même
 * traitement défensif que If-Match sur PATCH /invoices/{id}.
 */
final class RunComplianceAnalysisController
{
    public function __construct(
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly OrganizationRepository $organizationRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly RunComplianceAnalysisService $runComplianceAnalysisService,
    ) {
    }

    #[Route('/api/v1/invoices/{id}/compliance-analyses', name: 'compliance_analyses_run', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $idempotencyKey = $request->headers->get('Idempotency-Key');
        if (null === $idempotencyKey || '' === trim($idempotencyKey)) {
            throw new BadRequestHttpException('L\'en-tête Idempotency-Key est requis pour lancer une analyse de conformité.');
        }

        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException('Cette facture n\'existe pas ou n\'est plus disponible.');
        }

        $invoice = $this->invoiceRepository->find(Uuid::fromString($id));
        if (null === $invoice) {
            throw new NotFoundHttpException('Cette facture n\'existe pas ou n\'est plus disponible.');
        }

        $organization = $this->organizationRepository->find($this->currentOrganizationResolver->getOrganizationId());
        if (null === $organization) {
            throw new AuthenticatedIdentityWithoutOrganizationException('Resolved organization does not exist.');
        }

        $result = $this->runComplianceAnalysisService->run($organization, $invoice, $idempotencyKey);

        return new JsonResponse($result['body'], $result['status']);
    }
}
