<?php

declare(strict_types=1);

namespace App\Compliance\Service;

use App\Compliance\Rules\Repository\RuleVersionRepository;
use App\Compliance\Rules\RuleId;
use App\Organization\Enum\CompanySizeCategory;
use App\Shared\Exception\RegulatoryRuleVersionNotFoundException;

/**
 * Dérive CompanySizeCategory (docs/07-data-model.md, section 7) à partir des trois valeurs
 * brutes saisies par l'utilisateur (US-COMPANY-002), en appliquant le seuil PME/non-PME
 * versionné dans la RuleVersion "calendrier-obligation-emission" : jamais un seuil codé en
 * dur ici (voir plan Phase 3, gap 2, et la migration de seed pour le contenu exact de
 * `conditions`).
 *
 * Rappel important documenté dans le plan : `company_size_category` reste une
 * classification binaire propre au calendrier de la réforme (PME-ou-en-dessous vs le
 * reste), pas la classification légale INSEE à quatre niveaux : ce service ne cherche
 * jamais à distinguer ETI de grande entreprise, le produit n'en a pas l'usage.
 *
 * Comparaison des seuils monétaires via (float) plutôt que bcmath : l'extension bcmath
 * n'est pas installée (backend/Dockerfile, vérifié avant d'écrire ce code) et une
 * comparaison ponctuelle à un seuil (jamais une accumulation) sur des montants de cet ordre
 * de grandeur reste exacte en double précision IEEE 754 (52 bits de mantisse, largement
 * suffisant pour des montants à 16 chiffres significatifs) : un choix pragmatique, pas la
 * négligence générale envers les flottants que docs/08-api-specification.md proscrit pour
 * les montants transmis à l'API (ceux-ci restent des chaînes décimales de bout en bout).
 */
final class CompanySizeCategoryResolver
{
    public function __construct(
        private readonly RuleVersionRepository $ruleVersionRepository,
    ) {
    }

    public function resolve(
        int $employeesCount,
        string $annualTurnover,
        string $annualBalanceSheetTotal,
        \DateTimeImmutable $at,
    ): CompanySizeCategoryResolution {
        $ruleVersion = $this->ruleVersionRepository->findActive(RuleId::CALENDRIER_OBLIGATION_EMISSION, $at);

        if (null === $ruleVersion) {
            throw new RegulatoryRuleVersionNotFoundException(
                sprintf('No active RuleVersion for rule "%s" at %s.', RuleId::CALENDRIER_OBLIGATION_EMISSION, $at->format('Y-m-d')),
            );
        }

        $threshold = $ruleVersion->getConditions()['pme_threshold'];

        $isPmeTpeMicro = $employeesCount < (int) $threshold['employees_count_max_exclusive']
            && (
                (float) $annualTurnover <= (float) $threshold['annual_turnover_max']
                || (float) $annualBalanceSheetTotal <= (float) $threshold['annual_balance_sheet_total_max']
            );

        $category = $isPmeTpeMicro ? CompanySizeCategory::PME_TPE_MICRO : CompanySizeCategory::GRANDE_ENTREPRISE_ETI;

        return new CompanySizeCategoryResolution($category, $ruleVersion);
    }
}
