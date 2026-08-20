<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Service;

use App\Compliance\Engine\Entity\ComplianceAnalysis;
use App\Compliance\Engine\Entity\ComplianceFinding;
use App\Compliance\Engine\Entity\ContextSnapshot;
use App\Compliance\Engine\Http\ComplianceAnalysisView;
use App\Customer\Entity\Customer;
use App\Document\Repository\DocumentRepository;
use App\Identity\Entity\User;
use App\Invoicing\Entity\Invoice;
use App\Organization\Entity\Organization;
use App\Organization\Repository\FiscalContextRepository;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Idempotency\Service\IdempotencyStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Orchestration transactionnelle de POST /invoices/{id}/compliance-analyses, même style
 * que App\Organization\Service\ConfigureOrganizationService et
 * App\Invoicing\Service\InvoiceService.
 *
 * Toujours synchrone en Phase 5 (saisie manuelle uniquement, docs/08-api-specification.md
 * section 30 : le chemin asynchrone 202/status_url documenté reste hors périmètre tant que
 * Document/Phase 7 n'existe pas) : toujours 200 OK en sortie, jamais 202.
 */
final class RunComplianceAnalysisService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FiscalContextRepository $fiscalContextRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly ComplianceEngine $complianceEngine,
        private readonly AuditLogger $auditLogger,
        private readonly IdempotencyStore $idempotencyStore,
        private readonly Security $security,
    ) {
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function run(Organization $organization, Invoice $invoice, string $idempotencyKey): array
    {
        return $this->entityManager->wrapInTransaction(
            fn (): array => $this->idempotencyStore->execute(
                $organization->getId(),
                $idempotencyKey,
                fn (): array => $this->doRun($organization, $invoice),
            ),
        );
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function doRun(Organization $organization, Invoice $invoice): array
    {
        $fiscalContext = $this->fiscalContextRepository->findCurrent($organization->getId());

        if (null === $fiscalContext) {
            throw new ConflictHttpException("Configurez le contexte fiscal de votre organisation avant de lancer une analyse de conformité.");
        }

        $customer = $invoice->getCustomer();

        $analysis = new ComplianceAnalysis($organization, $invoice);
        $analysis->markRunning();

        $snapshot = new ContextSnapshot($analysis, $fiscalContext, $this->customerSnapshot($customer));

        // Le Document le plus récent non supprimé de l'Invoice (docs/07-data-model.md,
        // section 13 : plusieurs documents possibles) - App\Compliance\Engine\RuleCheck\
        // DocumentFormatRuleChecker n'a besoin que d'un seul candidat représentatif, jamais
        // de l'historique complet (plan Phase 7).
        $document = $this->documentRepository->findActiveForInvoice($invoice->getId())[0] ?? null;

        [$drafts, $globalResult] = $this->complianceEngine->evaluate($invoice, $customer, $fiscalContext, new \DateTimeImmutable(), $document);

        $findings = array_map(
            static fn (ComplianceFindingDraft $draft): ComplianceFinding => new ComplianceFinding(
                $analysis,
                $draft->ruleVersion,
                $draft->result,
                $draft->message,
                $draft->relatedField,
                $draft->observedValue,
                $draft->correctionAction,
            ),
            $drafts,
        );

        $analysis->markCompleted($globalResult, new \DateTimeImmutable());

        $this->entityManager->persist($analysis);
        $this->entityManager->persist($snapshot);
        foreach ($findings as $finding) {
            $this->entityManager->persist($finding);
        }

        $invoice->markAnalyzed();

        $this->auditLogger->record(
            $invoice->getOrganizationId(),
            ActorType::SYSTEM,
            $this->currentActorId(),
            EventType::COMPLIANCE_ANALYSIS_COMPLETED,
            'ComplianceAnalysis',
            $analysis->getId()->toRfc4122(),
            null,
            [
                'invoice_id' => $invoice->getId()->toRfc4122(),
                'global_result' => $globalResult->value,
                'findings_count' => \count($findings),
            ],
        );

        return ['status' => 200, 'body' => ['data' => ComplianceAnalysisView::fromEntity($analysis, $findings)]];
    }

    /** @return array<string, mixed> */
    private function customerSnapshot(Customer $customer): array
    {
        return [
            'id' => $customer->getId()->toRfc4122(),
            'customer_type' => $customer->getCustomerType()->value,
            'siren' => $customer->getSiren(),
            'vat_number' => $customer->getVatNumber(),
            'country' => $customer->getCountry(),
            'name' => $customer->getName(),
        ];
    }

    private function currentActorId(): ?Uuid
    {
        $currentUser = $this->security->getUser();

        return $currentUser instanceof User ? $currentUser->getId() : null;
    }
}
