<?php

declare(strict_types=1);

namespace App\Invoicing\Http;

use App\Invoicing\Enum\OperationType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /invoices (docs/08-api-specification.md, section 27). Construit manuellement
 * (::fromArray), jamais via #[MapRequestPayload] : voir le commentaire de
 * App\Invoicing\Http\InvoiceLineInput pour la limitation vérifiée de PropertyInfo sur ce
 * projet (pas de résolution de type d'élément de tableau de DTO sans phpdocumentor/phpstan).
 *
 * Pas de champ Idempotency-Key porté ici : POST /invoices n'honore pas encore cet en-tête
 * en Phase 4 (plan Phase 4, décision D2 - écart connu documenté avec CLAUDE.md racine
 * section 11, différé à l'intégration Redis/Messenger de la Phase 7).
 *
 * `currency` validé par `#[Assert\Currency]` (liste ISO 4217 réelle) : nécessite le
 * composant `symfony/intl`, installé (composer require symfony/intl, v8.1.4, aligné sur le
 * reste de la stack Symfony 8.1.*).
 */
final readonly class CreateInvoiceRequest
{
    /** @param list<InvoiceLineInput> $lines */
    public function __construct(
        #[Assert\NotBlank(message: 'Le client (customer_id) est requis.')]
        #[Assert\Uuid(message: 'customer_id doit être un UUID valide.')]
        public string $customerId,
        #[Assert\NotNull(message: "La nature de l'opération (operation_type) est requise.")]
        #[Assert\Choice(callback: [OperationType::class, 'values'], message: "Nature de l'opération invalide.")]
        public ?string $operationType,
        #[Assert\NotBlank(message: "La date d'émission (issue_date) est requise.")]
        #[Assert\Date(message: 'issue_date doit être une date valide (YYYY-MM-DD).')]
        public string $issueDate,
        #[Assert\NotBlank(message: 'La devise (currency) est requise.')]
        #[Assert\Currency(message: 'currency doit être un code devise ISO 4217 valide.')]
        public string $currency,
        #[Assert\Count(min: 1, minMessage: 'Une facture doit contenir au moins une ligne.')]
        #[Assert\Valid]
        public array $lines,
        public ?string $invoiceNumber,
        public ?string $vatExemptionReason,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $rawLines = \is_array($payload['lines'] ?? null) ? $payload['lines'] : [];

        return new self(
            self::stringify($payload['customer_id'] ?? null),
            \is_string($payload['operation_type'] ?? null) ? $payload['operation_type'] : null,
            self::stringify($payload['issue_date'] ?? null),
            self::stringify($payload['currency'] ?? null),
            array_map(
                static fn (mixed $rawLine): InvoiceLineInput => InvoiceLineInput::fromArray(\is_array($rawLine) ? $rawLine : []),
                array_values($rawLines),
            ),
            \is_string($payload['invoice_number'] ?? null) ? $payload['invoice_number'] : null,
            \is_string($payload['vat_exemption_reason'] ?? null) ? $payload['vat_exemption_reason'] : null,
        );
    }

    private static function stringify(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
