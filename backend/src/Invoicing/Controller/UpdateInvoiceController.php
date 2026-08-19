<?php

declare(strict_types=1);

namespace App\Invoicing\Controller;

use App\Invoicing\Entity\Invoice;
use App\Invoicing\Http\InvoiceView;
use App\Invoicing\Service\InvoiceService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * PATCH /invoices/{id} (docs/08-api-specification.md, section 21, 27). Verrouillage
 * optimiste natif Doctrine (plan Phase 4, décision D3), vérifié contre le code source
 * installé (doctrine/orm 3.6) : EntityManagerInterface::find() avec LockMode::OPTIMISTIC et
 * la version attendue lève Doctrine\ORM\OptimisticLockException en cas de désaccord, capturée
 * ici et traduite en 409 Conflict - jamais un détail technique exposé au client
 * (backend/CLAUDE.md, section 12).
 *
 * Distinct de la transition ANALYZED -> ANALYSIS_STALE (docs/08-api-specification.md, section
 * 28) : cette transition n'est pas atteignable en Phase 4 (aucune ComplianceAnalysis n'existe
 * avant Phase 5), donc jamais produite par ce controller aujourd'hui.
 */
final class UpdateInvoiceController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvoiceService $invoiceService,
    ) {
    }

    #[Route('/api/v1/invoices/{id}', name: 'invoices_update', methods: ['PATCH'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException('Cette facture n\'existe pas ou n\'est plus disponible.');
        }

        $expectedVersion = $this->parseIfMatch($request);
        if (null === $expectedVersion) {
            throw new ConflictHttpException('L\'en-tête If-Match est requis pour modifier cette facture.');
        }

        try {
            $invoice = $this->entityManager->find(Invoice::class, Uuid::fromString($id), LockMode::OPTIMISTIC, $expectedVersion);
        } catch (OptimisticLockException) {
            throw new ConflictHttpException('Cette facture a été modifiée depuis sa dernière lecture ; rechargez-la avant de réessayer.');
        }

        if (null === $invoice) {
            throw new NotFoundHttpException('Cette facture n\'existe pas ou n\'est plus disponible.');
        }

        $invoice = $this->invoiceService->update($invoice, $request->toArray());

        $response = new JsonResponse(['data' => InvoiceView::fromEntity($invoice)]);
        $response->headers->set('ETag', sprintf('W/"%d"', $invoice->getVersion()));

        return $response;
    }

    private function parseIfMatch(Request $request): ?int
    {
        $header = $request->headers->get('If-Match');

        if (null === $header || 1 !== preg_match('/^(?:W\/)?"(\d+)"$/', trim($header), $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
