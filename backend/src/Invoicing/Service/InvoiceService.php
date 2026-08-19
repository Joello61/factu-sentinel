<?php

declare(strict_types=1);

namespace App\Invoicing\Service;

use App\Customer\Repository\CustomerRepository;
use App\Identity\Entity\User;
use App\Invoicing\Entity\Invoice;
use App\Invoicing\Entity\InvoiceLine;
use App\Invoicing\Enum\InvoiceSource;
use App\Invoicing\Enum\OperationType;
use App\Invoicing\Http\CreateInvoiceRequest;
use App\Invoicing\Http\InvoiceLineInput;
use App\Invoicing\Http\UpdateInvoiceInput;
use App\Organization\Entity\Organization;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Orchestration transactionnelle de POST/PATCH /invoices, même style que
 * App\Organization\Service\ConfigureOrganizationService et App\Customer\Service\CustomerService.
 *
 * Résout toujours customer_id via CustomerRepository::findActive, jamais en confiance :
 * TenantFilter (déjà actif) exclut automatiquement un client d'une autre organisation, ce
 * qui produit ici un 404 (jamais 403, backend/CLAUDE.md section 6 ; invariant multi-tenant
 * docs/07-data-model.md section 28 : aucune relation ne doit traverser deux organization_id).
 */
final class InvoiceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerRepository $customerRepository,
        private readonly InvoiceAmountCalculator $amountCalculator,
        private readonly AuditLogger $auditLogger,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
    ) {
    }

    /**
     * @throws ConflictHttpException si `(organization_id, invoice_number)` existe déjà
     *                                (contrainte unique, docs/07-data-model.md section 28)
     */
    public function create(Organization $organization, CreateInvoiceRequest $request): Invoice
    {
        try {
            return $this->doCreate($organization, $request);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Une facture avec ce numéro existe déjà pour cette organisation.');
        }
    }

    private function doCreate(Organization $organization, CreateInvoiceRequest $request): Invoice
    {
        return $this->entityManager->wrapInTransaction(function () use ($organization, $request): Invoice {
            $customer = $this->resolveCustomer($request->customerId);

            \assert(null !== $request->operationType);

            $invoice = new Invoice(
                $organization,
                $customer,
                $request->invoiceNumber,
                new \DateTimeImmutable($request->issueDate),
                OperationType::from($request->operationType),
                $request->currency,
                $request->vatExemptionReason,
                InvoiceSource::SAISIE_MANUELLE,
            );

            $this->applyLines($invoice, $request->lines);
            $invoice->refreshReadinessStatus();

            $this->entityManager->persist($invoice);

            $this->auditLogger->record(
                $organization->getId(),
                ActorType::USER,
                $this->currentActorId(),
                EventType::INVOICE_CREATED,
                'Invoice',
                $invoice->getId()->toRfc4122(),
                null,
                ['status' => $invoice->getStatus()->value, 'customer_id' => $customer->getId()->toRfc4122()],
            );

            return $invoice;
        });
    }

    /** @param array<string, mixed> $payload */
    public function update(Invoice $invoice, array $payload): Invoice
    {
        return $this->entityManager->wrapInTransaction(function () use ($invoice, $payload): Invoice {
            $previousStatus = $invoice->getStatus()->value;

            $input = new UpdateInvoiceInput(
                \array_key_exists('customer_id', $payload) ? (string) $payload['customer_id'] : $invoice->getCustomer()->getId()->toRfc4122(),
                \array_key_exists('operation_type', $payload) ? $payload['operation_type'] : $invoice->getOperationType()->value,
                \array_key_exists('issue_date', $payload) ? (string) $payload['issue_date'] : $invoice->getIssueDate()->format('Y-m-d'),
                \array_key_exists('currency', $payload) ? (string) $payload['currency'] : $invoice->getCurrency(),
                \array_key_exists('lines', $payload) && \is_array($payload['lines'])
                    ? array_map(
                        static fn (mixed $rawLine): InvoiceLineInput => InvoiceLineInput::fromArray(\is_array($rawLine) ? $rawLine : []),
                        array_values($payload['lines']),
                    )
                    : null,
                \array_key_exists('invoice_number', $payload) ? $payload['invoice_number'] : $invoice->getInvoiceNumber(),
                \array_key_exists('vat_exemption_reason', $payload) ? $payload['vat_exemption_reason'] : $invoice->getVatExemptionReason(),
            );

            $violations = $this->validator->validate($input);
            if (\count($violations) > 0) {
                throw new ValidationFailedException($input, $violations);
            }

            \assert(\is_string($input->operationType));

            // US-COMPLIANCE-006bis : une modification effective d'une facture ANALYZED la
            // rend obsolète (Invoice::markStale()) -- suivi champ par champ plutôt que sur
            // la seule présence de la clé dans le payload (PATCH peut renvoyer la même
            // valeur qu'avant sans que ce soit une vraie modification).
            $dataChanged = false;

            if ($input->customerId !== $invoice->getCustomer()->getId()->toRfc4122()) {
                $invoice->setCustomer($this->resolveCustomer($input->customerId));
                $dataChanged = true;
            }

            if ($input->invoiceNumber !== $invoice->getInvoiceNumber()) {
                $invoice->setInvoiceNumber($input->invoiceNumber);
                $dataChanged = true;
            }

            if ($input->issueDate !== $invoice->getIssueDate()->format('Y-m-d')) {
                $invoice->setIssueDate(new \DateTimeImmutable($input->issueDate));
                $dataChanged = true;
            }

            if ($input->operationType !== $invoice->getOperationType()->value) {
                $invoice->setOperationType(OperationType::from($input->operationType));
                $dataChanged = true;
            }

            if ($input->currency !== $invoice->getCurrency()) {
                $invoice->setCurrency($input->currency);
                $dataChanged = true;
            }

            if ($input->vatExemptionReason !== $invoice->getVatExemptionReason()) {
                $invoice->setVatExemptionReason($input->vatExemptionReason);
                $dataChanged = true;
            }

            if (null !== $input->lines) {
                $this->applyLines($invoice, $input->lines);
                $dataChanged = true;
            }

            if ($dataChanged) {
                $invoice->markStale();
            }

            $invoice->refreshReadinessStatus();
            $invoice->touch();

            $this->auditLogger->record(
                $invoice->getOrganizationId(),
                ActorType::USER,
                $this->currentActorId(),
                EventType::INVOICE_UPDATED,
                'Invoice',
                $invoice->getId()->toRfc4122(),
                ['status' => $previousStatus],
                ['status' => $invoice->getStatus()->value],
            );

            return $invoice;
        });
    }

    private function resolveCustomer(string $customerId): \App\Customer\Entity\Customer
    {
        if (!Uuid::isValid($customerId)) {
            throw new NotFoundHttpException('Ce client n\'existe pas ou n\'est plus disponible.');
        }

        $customer = $this->customerRepository->findActive(Uuid::fromString($customerId));

        if (null === $customer) {
            throw new NotFoundHttpException('Ce client n\'existe pas ou n\'est plus disponible.');
        }

        return $customer;
    }

    /** @param list<InvoiceLineInput> $lines */
    private function applyLines(Invoice $invoice, array $lines): void
    {
        $amounts = $this->amountCalculator->calculate($lines);

        $invoiceLines = array_map(
            static fn (CalculatedInvoiceLine $line): InvoiceLine => new InvoiceLine(
                $invoice,
                $line->description,
                $line->quantity,
                $line->unitPriceHt,
                $line->vatRate,
                $line->lineAmountHt,
                $line->lineAmountVat,
                $line->lineAmountTtc,
            ),
            $amounts->lines,
        );

        $invoice->replaceLines(...$invoiceLines);
        $invoice->setTotals($amounts->totalAmountHt, $amounts->totalAmountTtc);
    }

    private function currentActorId(): ?Uuid
    {
        $currentUser = $this->security->getUser();

        return $currentUser instanceof User ? $currentUser->getId() : null;
    }
}
