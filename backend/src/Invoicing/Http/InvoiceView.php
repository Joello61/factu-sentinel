<?php

declare(strict_types=1);

namespace App\Invoicing\Http;

use App\Invoicing\Entity\Invoice;
use App\Invoicing\Entity\InvoiceLine;

/**
 * Forme JSON partagée par les controllers Invoice (docs/08-api-specification.md, section
 * 27) : évite de dupliquer la liste de champs.
 */
final class InvoiceView
{
    /** @return array<string, mixed> */
    public static function fromEntity(Invoice $invoice): array
    {
        return [
            'id' => $invoice->getId()->toRfc4122(),
            'customer_id' => $invoice->getCustomer()->getId()->toRfc4122(),
            'invoice_number' => $invoice->getInvoiceNumber(),
            'issue_date' => $invoice->getIssueDate()->format('Y-m-d'),
            'operation_type' => $invoice->getOperationType()->value,
            'currency' => $invoice->getCurrency(),
            'total_amount_ht' => $invoice->getTotalAmountHt(),
            'total_amount_ttc' => $invoice->getTotalAmountTtc(),
            'vat_exemption_reason' => $invoice->getVatExemptionReason(),
            'status' => $invoice->getStatus()->value,
            'source' => $invoice->getSource()->value,
            'lines' => array_map(self::lineToArray(...), $invoice->getLines()->toArray()),
            'created_at' => $invoice->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $invoice->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private static function lineToArray(InvoiceLine $line): array
    {
        return [
            'id' => $line->getId()->toRfc4122(),
            'description' => $line->getDescription(),
            'quantity' => $line->getQuantity(),
            'unit_price_ht' => $line->getUnitPriceHt(),
            'vat_rate' => $line->getVatRate(),
            'line_amount_ht' => $line->getLineAmountHt(),
            'line_amount_vat' => $line->getLineAmountVat(),
            'line_amount_ttc' => $line->getLineAmountTtc(),
        ];
    }
}
