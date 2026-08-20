<?php

declare(strict_types=1);

namespace App\Invoicing\Http;

use App\Document\Entity\Document;
use App\Document\Http\DocumentView;
use App\Invoicing\Entity\Invoice;
use App\Invoicing\Entity\InvoiceLine;

/**
 * Forme JSON partagée par les controllers Invoice (docs/08-api-specification.md, section
 * 27) : évite de dupliquer la liste de champs.
 */
final class InvoiceView
{
    /**
     * $documents (Phase 7, docs/12-roadmap.md ; docs/11-frontend-design-system.md section
     * 32 : "Détail : ... ses documents associés") : jamais chargé par cette classe elle-même
     * (une App\Invoicing\Http\* ne dépend jamais de Doctrine) - fourni par l'appelant, qui
     * décide s'il a besoin de cette composition (même précédent que
     * App\Compliance\Engine\Controller\RunComplianceAnalysisController, qui injecte déjà des
     * repositories d'autres modules pour orchestrer une réponse). Vide par défaut : seul
     * App\Invoicing\Controller\GetInvoiceController (page de détail) le renseigne
     * réellement, les autres endpoints Invoice n'ont pas besoin de cette composition.
     *
     * @param list<Document> $documents
     *
     * @return array<string, mixed>
     */
    public static function fromEntity(Invoice $invoice, array $documents = []): array
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
            'documents' => array_map(DocumentView::fromEntity(...), $documents),
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
