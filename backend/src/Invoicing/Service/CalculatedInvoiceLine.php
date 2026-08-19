<?php

declare(strict_types=1);

namespace App\Invoicing\Service;

/** Sortie pure de App\Invoicing\Service\InvoiceAmountCalculator pour une ligne. */
final readonly class CalculatedInvoiceLine
{
    public function __construct(
        public string $description,
        public string $quantity,
        public string $unitPriceHt,
        public string $vatRate,
        public string $lineAmountHt,
        public string $lineAmountVat,
        public string $lineAmountTtc,
    ) {
    }
}
