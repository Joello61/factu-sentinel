<?php

declare(strict_types=1);

namespace App\Invoicing\Http;

use App\Invoicing\Enum\OperationType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente l'Invoice après fusion de la requête PATCH avec l'état existant
 * (App\Invoicing\Service\InvoiceService::update()), même sémantique que
 * App\Organization\Http\MergedFiscalContextInput : distingue champ absent de champ
 * explicitement null. `lines`, si fourni, remplace intégralement les lignes existantes
 * (docs/08-api-specification.md, section 19) ; si absent de la requête PATCH, les lignes
 * existantes sont conservées telles quelles.
 */
final readonly class UpdateInvoiceInput
{
    /** @param list<InvoiceLineInput>|null $lines */
    public function __construct(
        #[Assert\NotBlank(message: 'Le client (customer_id) est requis.')]
        #[Assert\Uuid(message: 'customer_id doit être un UUID valide.')]
        public string $customerId,
        #[Assert\NotNull(message: "La nature de l'opération (operation_type) est requise.")]
        #[Assert\Choice(callback: [OperationType::class, 'values'], message: "Nature de l'opération invalide.")]
        public ?string $operationType,
        #[Assert\NotBlank(message: "La date d'émission (issue_date) est requise.")]
        public string $issueDate,
        #[Assert\NotBlank(message: 'La devise (currency) est requise.')]
        #[Assert\Currency(message: 'currency doit être un code devise ISO 4217 valide.')]
        public string $currency,
        #[Assert\Valid]
        public ?array $lines,
        public ?string $invoiceNumber,
        public ?string $vatExemptionReason,
    ) {
    }
}
