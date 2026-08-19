<?php

declare(strict_types=1);

namespace App\Tests\Integration\Invoicing;

use App\Invoicing\Http\InvoiceLineInput;
use App\Invoicing\Service\InvoiceAmountCalculator;
use PHPUnit\Framework\TestCase;

/**
 * REG-005 (docs/09-test-strategy.md, section 9) : facture avec plusieurs taux de TVA sur
 * des lignes distinctes -> totaux HT/TVA/TTC cohérents par ligne et agrégés
 * (docs/07-data-model.md, section 11). Service pur, sans dépendance : test unitaire
 * indépendant du kernel Symfony (plus rapide que les tests d'intégration/fonctionnels).
 */
final class InvoiceAmountCalculatorTest extends TestCase
{
    private InvoiceAmountCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new InvoiceAmountCalculator();
    }

    public function testMultipleVatRatesProduceConsistentTotals(): void
    {
        $result = $this->calculator->calculate([
            new InvoiceLineInput('Prestation A', '1', '100.00', '0.20'),
            new InvoiceLineInput('Prestation B', '2', '50.00', '0.055'),
        ]);

        self::assertCount(2, $result->lines);

        $lineA = $result->lines[0];
        self::assertSame('100.00', $lineA->lineAmountHt);
        self::assertSame('20.00', $lineA->lineAmountVat);
        self::assertSame('120.00', $lineA->lineAmountTtc);

        $lineB = $result->lines[1];
        self::assertSame('100.00', $lineB->lineAmountHt);
        self::assertSame('5.50', $lineB->lineAmountVat);
        self::assertSame('105.50', $lineB->lineAmountTtc);

        self::assertSame('200.00', $result->totalAmountHt);
        self::assertSame('225.50', $result->totalAmountTtc);
    }

    public function testEachLineHtPlusVatEqualsTtc(): void
    {
        $result = $this->calculator->calculate([
            new InvoiceLineInput('Ligne', '3', '33.33', '0.20'),
        ]);

        $line = $result->lines[0];
        self::assertSame(
            number_format((float) $line->lineAmountHt + (float) $line->lineAmountVat, 2, '.', ''),
            $line->lineAmountTtc,
        );
    }

    public function testTotalsAreTheSumOfLineAmounts(): void
    {
        $result = $this->calculator->calculate([
            new InvoiceLineInput('Ligne 1', '1', '10.00', '0.20'),
            new InvoiceLineInput('Ligne 2', '1', '20.00', '0.20'),
            new InvoiceLineInput('Ligne 3', '1', '30.00', '0.055'),
        ]);

        $sumHt = 0.0;
        $sumTtc = 0.0;
        foreach ($result->lines as $line) {
            $sumHt += (float) $line->lineAmountHt;
            $sumTtc += (float) $line->lineAmountTtc;
        }

        self::assertSame(number_format($sumHt, 2, '.', ''), $result->totalAmountHt);
        self::assertSame(number_format($sumTtc, 2, '.', ''), $result->totalAmountTtc);
    }

    public function testZeroVatRateExemptionProducesZeroVatAmount(): void
    {
        $result = $this->calculator->calculate([
            new InvoiceLineInput('Prestation exonérée', '1', '100.00', '0'),
        ]);

        self::assertSame('0.00', $result->lines[0]->lineAmountVat);
        self::assertSame('100.00', $result->lines[0]->lineAmountTtc);
    }
}
