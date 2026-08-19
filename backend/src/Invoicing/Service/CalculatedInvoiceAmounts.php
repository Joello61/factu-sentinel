<?php

declare(strict_types=1);

namespace App\Invoicing\Service;

/** Sortie pure de App\Invoicing\Service\InvoiceAmountCalculator. */
final readonly class CalculatedInvoiceAmounts
{
    /** @param list<CalculatedInvoiceLine> $lines */
    public function __construct(
        public array $lines,
        public string $totalAmountHt,
        public string $totalAmountTtc,
    ) {
    }
}
