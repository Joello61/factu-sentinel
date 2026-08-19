<?php

declare(strict_types=1);

namespace App\Invoicing\Service;

use App\Invoicing\Http\InvoiceLineInput;

/**
 * Service pur (mêmes principes que App\Compliance\Service\CompanySizeCategoryResolver) :
 * calcule les montants d'une facture à partir des lignes saisies, jamais des montants fournis
 * par le client (docs/08-api-specification.md, section 27, l'exemple de requête n'inclut
 * jamais line_amount_ht/vat/ttc). Incarne l'invariant de docs/07-data-model.md section 11 :
 * `line_amount_ht = quantity * unit_price_ht`, `line_amount_vat = line_amount_ht * vat_rate`,
 * `line_amount_ttc = line_amount_ht + line_amount_vat`, `total_amount_* = Σ line_amount_*`.
 *
 * Couvre le scénario REG-005 (docs/09-test-strategy.md, section 9 : plusieurs lignes à taux
 * de TVA distincts sur une même facture).
 *
 * Utilise (float)/round(), pas bcmath : précédent déjà établi et documenté dans
 * CompanySizeCategoryResolver (bcmath non installé, revérifié dans backend/Dockerfile pour
 * cette tâche). Montants exprimés en chaînes décimales à 2 décimales (docs/08-api-specification.md,
 * section 18), quantité à 3 décimales, taux de TVA à 4 décimales (fraction, pas un pourcentage).
 */
final class InvoiceAmountCalculator
{
    /**
     * @param list<InvoiceLineInput> $lines
     */
    public function calculate(array $lines): CalculatedInvoiceAmounts
    {
        $calculatedLines = [];
        $totalHt = 0.0;
        $totalTtc = 0.0;

        foreach ($lines as $line) {
            $quantity = (float) $line->quantity;
            $unitPriceHt = (float) $line->unitPriceHt;
            $vatRate = (float) $line->vatRate;

            $lineAmountHt = round($quantity * $unitPriceHt, 2);
            $lineAmountVat = round($lineAmountHt * $vatRate, 2);
            $lineAmountTtc = $lineAmountHt + $lineAmountVat;

            $calculatedLines[] = new CalculatedInvoiceLine(
                $line->description,
                self::toAmountString($quantity, 3),
                self::toAmountString($unitPriceHt, 2),
                self::toAmountString($vatRate, 4),
                self::toAmountString($lineAmountHt, 2),
                self::toAmountString($lineAmountVat, 2),
                self::toAmountString($lineAmountTtc, 2),
            );

            $totalHt += $lineAmountHt;
            $totalTtc += $lineAmountTtc;
        }

        return new CalculatedInvoiceAmounts($calculatedLines, self::toAmountString($totalHt, 2), self::toAmountString($totalTtc, 2));
    }

    private static function toAmountString(float $value, int $scale): string
    {
        return number_format($value, $scale, '.', '');
    }
}
