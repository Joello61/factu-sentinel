<?php

declare(strict_types=1);

namespace App\Tests\Integration\Compliance;

use App\Compliance\Rules\Repository\RuleVersionRepository;
use App\Compliance\Rules\RuleId;
use App\Compliance\Service\CompanySizeCategoryResolver;
use App\Compliance\Service\EligibilityDiagnosticCalculator;
use App\Organization\Entity\FiscalContext;
use App\Organization\Entity\Organization;
use App\Organization\Enum\CompanySizeCategory;
use App\Organization\Enum\VatStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Contre le référentiel réel seedé par la migration Phase 3 (docs/09-test-strategy.md,
 * section 9 : REG-001, REG-009 ; section 11 : déterminisme) — pas de double contre les
 * seuils, ils sont lus depuis Compliance/Rules comme en production, jamais recopiés en dur
 * ici (voir plan Phase 3).
 */
final class EligibilityDiagnosticCalculatorTest extends KernelTestCase
{
    private EligibilityDiagnosticCalculator $calculator;
    private CompanySizeCategoryResolver $companySizeCategoryResolver;
    private RuleVersionRepository $ruleVersionRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->calculator = $container->get(EligibilityDiagnosticCalculator::class);
        $this->companySizeCategoryResolver = $container->get(CompanySizeCategoryResolver::class);
        $this->ruleVersionRepository = $container->get(RuleVersionRepository::class);
    }

    private function fiscalContext(
        Organization $organization,
        VatStatus $vatStatus,
        int $employeesCount,
        string $annualTurnover,
        string $annualBalanceSheetTotal,
        \DateTimeImmutable $at,
    ): FiscalContext {
        $category = $this->companySizeCategoryResolver->resolve($employeesCount, $annualTurnover, $annualBalanceSheetTotal, $at)->category;

        return new FiscalContext($organization, $vatStatus, $employeesCount, $annualTurnover, $annualBalanceSheetTotal, $category, $at);
    }

    private function calendarRuleVersion(\DateTimeImmutable $at): \App\Compliance\Rules\Entity\RuleVersion
    {
        $ruleVersion = $this->ruleVersionRepository->findActive(RuleId::ELIGIBILITE_CALENDRIER_TAILLE, $at);
        self::assertNotNull($ruleVersion, 'RuleVersion "eligibilite-calendrier-taille" doit être seedée par la migration Phase 3.');

        return $ruleVersion;
    }

    /** REG-001 : franchise en base reste explicitement concernée (BR-ELIGIBILITY-001). */
    public function testReg001FranchiseEnBaseRemainsInScope(): void
    {
        $organization = new Organization();
        $at = new \DateTimeImmutable('2026-08-19');
        $fiscalContext = $this->fiscalContext($organization, VatStatus::ASSUJETTI_FRANCHISE_EN_BASE, 3, '50000', '30000', $at);

        $diagnostic = $this->calculator->calculate($organization, $fiscalContext, $this->calendarRuleVersion($at), $at);

        self::assertSame('2026-09-01', $diagnostic->getReceptionObligationDate()?->format('Y-m-d'));
        self::assertStringContainsString('franchise en base', $diagnostic->getExplanation());
        self::assertStringContainsString('assujetti', $diagnostic->getExplanation());
    }

    /** REG-009 : taille PME/TPE/micro => émission 2027, jamais 2026. */
    public function testReg009PmeTpeMicroEmissionDateIs2027(): void
    {
        $organization = new Organization();
        $at = new \DateTimeImmutable('2026-08-19');
        $fiscalContext = $this->fiscalContext($organization, VatStatus::ASSUJETTI_REDEVABLE, 5, '200000', '150000', $at);

        $diagnostic = $this->calculator->calculate($organization, $fiscalContext, $this->calendarRuleVersion($at), $at);

        self::assertSame(CompanySizeCategory::PME_TPE_MICRO, $fiscalContext->getCompanySizeCategory());
        self::assertSame('2027-09-01', $diagnostic->getEmissionObligationDate()?->format('Y-m-d'));
    }

    public function testGrandeEntrepriseEtiEmissionDateIs2026(): void
    {
        $organization = new Organization();
        $at = new \DateTimeImmutable('2026-08-19');
        $fiscalContext = $this->fiscalContext($organization, VatStatus::ASSUJETTI_REDEVABLE, 5000, '2000000000', '2100000000', $at);

        $diagnostic = $this->calculator->calculate($organization, $fiscalContext, $this->calendarRuleVersion($at), $at);

        self::assertSame(CompanySizeCategory::GRANDE_ENTREPRISE_ETI, $fiscalContext->getCompanySizeCategory());
        self::assertSame('2026-09-01', $diagnostic->getEmissionObligationDate()?->format('Y-m-d'));
    }

    /** non_assujetti : hors périmètre, jamais confondu avec la franchise en base. */
    public function testNonAssujettiIsOutOfScope(): void
    {
        $organization = new Organization();
        $at = new \DateTimeImmutable('2026-08-19');
        $fiscalContext = $this->fiscalContext($organization, VatStatus::NON_ASSUJETTI, 2, '10000', '5000', $at);

        $diagnostic = $this->calculator->calculate($organization, $fiscalContext, $this->calendarRuleVersion($at), $at);

        self::assertNull($diagnostic->getReceptionObligationDate());
        self::assertNull($diagnostic->getEmissionObligationDate());
        self::assertStringContainsString("n'est pas assujettie", $diagnostic->getExplanation());
    }

    public function testDeterminism(): void
    {
        $organization = new Organization();
        $at = new \DateTimeImmutable('2026-08-19');
        $fiscalContext = $this->fiscalContext($organization, VatStatus::ASSUJETTI_FRANCHISE_EN_BASE, 5, '200000', '150000', $at);
        $calendarRuleVersion = $this->calendarRuleVersion($at);

        $first = $this->calculator->calculate($organization, $fiscalContext, $calendarRuleVersion, $at);
        $second = $this->calculator->calculate($organization, $fiscalContext, $calendarRuleVersion, $at);

        self::assertSame($first->getReceptionObligationDate()?->format('Y-m-d'), $second->getReceptionObligationDate()?->format('Y-m-d'));
        self::assertSame($first->getEmissionObligationDate()?->format('Y-m-d'), $second->getEmissionObligationDate()?->format('Y-m-d'));
        self::assertSame($first->getExplanation(), $second->getExplanation());
    }

    /**
     * @return iterable<string, array{employees: int, turnover: string, balance: string, expected: CompanySizeCategory}>
     */
    public static function pmeThresholdProvider(): iterable
    {
        // Bornes sur l'effectif (250 exclusif), montants confortablement sous les seuils.
        yield '249 employés, sous les seuils monétaires' => ['employees' => 249, 'turnover' => '10000000', 'balance' => '10000000', 'expected' => CompanySizeCategory::PME_TPE_MICRO];
        yield '250 employés (seuil inclus côté non-PME)' => ['employees' => 250, 'turnover' => '10000000', 'balance' => '10000000', 'expected' => CompanySizeCategory::GRANDE_ENTREPRISE_ETI];
        yield '251 employés' => ['employees' => 251, 'turnover' => '10000000', 'balance' => '10000000', 'expected' => CompanySizeCategory::GRANDE_ENTREPRISE_ETI];

        // Bornes sur le chiffre d'affaires (50 000 000 inclus côté PME), bilan sous son seuil.
        yield 'CA exactement 50 000 000' => ['employees' => 100, 'turnover' => '50000000', 'balance' => '10000000', 'expected' => CompanySizeCategory::PME_TPE_MICRO];
        yield 'CA 50 000 001, bilan sous son seuil (OR)' => ['employees' => 100, 'turnover' => '50000001', 'balance' => '10000000', 'expected' => CompanySizeCategory::PME_TPE_MICRO];

        // Bornes sur le bilan (43 000 000 inclus côté PME), CA sous son seuil.
        yield 'Bilan exactement 43 000 000' => ['employees' => 100, 'turnover' => '10000000', 'balance' => '43000000', 'expected' => CompanySizeCategory::PME_TPE_MICRO];
        yield 'Bilan 43 000 001, CA sous son seuil (OR)' => ['employees' => 100, 'turnover' => '10000000', 'balance' => '43000001', 'expected' => CompanySizeCategory::PME_TPE_MICRO];

        // Combinaisons explicites exerçant le OR entre les deux critères monétaires, à
        // effectif constant sous le seuil de 250 (docs plan Phase 3, point 9 de la revue
        // utilisateur) : l'effectif reste un ET ferme avec le OR monétaire, jamais lui-même
        // en OR avec les montants — un effectif de 300 exclurait la catégorie PME quels que
        // soient le CA et le bilan, ce serait donc un mauvais cas pour isoler le OR monétaire.
        yield 'CA au-dessus, bilan en-dessous => PME (OR)' => ['employees' => 100, 'turnover' => '60000000', 'balance' => '40000000', 'expected' => CompanySizeCategory::PME_TPE_MICRO];
        yield 'CA en-dessous, bilan au-dessus => PME (OR)' => ['employees' => 100, 'turnover' => '40000000', 'balance' => '50000000', 'expected' => CompanySizeCategory::PME_TPE_MICRO];
        yield 'CA et bilan tous deux au-dessus => non-PME' => ['employees' => 100, 'turnover' => '60000000', 'balance' => '50000000', 'expected' => CompanySizeCategory::GRANDE_ENTREPRISE_ETI];
        // Effectif au-dessus du seuil à lui seul : non-PME même avec CA et bilan très bas,
        // le ET sur l'effectif prime sur le OR monétaire.
        yield 'Effectif au-dessus du seuil seul => non-PME malgré CA/bilan bas' => ['employees' => 300, 'turnover' => '1000', 'balance' => '1000', 'expected' => CompanySizeCategory::GRANDE_ENTREPRISE_ETI];
    }

    #[DataProvider('pmeThresholdProvider')]
    public function testPmeThreshold(int $employees, string $turnover, string $balance, CompanySizeCategory $expected): void
    {
        $at = new \DateTimeImmutable('2026-08-19');

        $resolution = $this->companySizeCategoryResolver->resolve($employees, $turnover, $balance, $at);

        self::assertSame($expected, $resolution->category);
    }
}
