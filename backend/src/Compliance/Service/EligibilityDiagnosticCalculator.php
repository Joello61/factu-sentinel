<?php

declare(strict_types=1);

namespace App\Compliance\Service;

use App\Compliance\Entity\EligibilityDiagnostic;
use App\Compliance\Rules\Entity\RuleVersion;
use App\Compliance\Rules\Repository\RuleVersionRepository;
use App\Compliance\Rules\RuleId;
use App\Organization\Entity\FiscalContext;
use App\Organization\Entity\Organization;
use App\Organization\Enum\CompanySizeCategory;
use App\Organization\Enum\VatStatus;
use App\Shared\Exception\RegulatoryRuleVersionNotFoundException;

/**
 * Service de calcul PUR (docs plan Phase 3, point 11 de la revue utilisateur) : ne
 * persiste rien, n'ouvre aucune transaction, ne connaît pas l'audit : dépend uniquement de
 * Compliance/Rules (lecture) et des valeurs d'un FiscalContext déjà résolu, rien d'autre.
 * La persistance, la transaction et les écritures d'audit sont la responsabilité de
 * App\Organization\Service\ConfigureOrganizationService, jamais de ce service.
 *
 * Chaque appel à la même date, avec le même FiscalContext et les mêmes RuleVersion
 * actives, doit produire un EligibilityDiagnostic strictement identique
 * (docs/09-test-strategy.md, section 11, déterminisme) : voir les tests dédiés.
 *
 * Traite explicitement le cas VatStatus::NON_ASSUJETTI comme hors périmètre de la réforme
 * (docs/02-regulatory-study.md, section 6, "Entreprises hors périmètre" : le champ
 * d'application vise les entreprises assujetties à la TVA) : distinct du cas
 * ASSUJETTI_FRANCHISE_EN_BASE, qui reste explicitement concerné (BR-ELIGIBILITY-001).
 */
final class EligibilityDiagnosticCalculator
{
    public function __construct(
        private readonly RuleVersionRepository $ruleVersionRepository,
    ) {
    }

    public function calculate(
        Organization $organization,
        FiscalContext $fiscalContext,
        RuleVersion $calendarRuleVersion,
        \DateTimeImmutable $at,
    ): EligibilityDiagnostic {
        $franchiseRuleVersion = $this->ruleVersionRepository->findActive(RuleId::FRANCHISE_EN_BASE_ELIGIBILITE, $at);

        if (null === $franchiseRuleVersion) {
            throw new RegulatoryRuleVersionNotFoundException(
                sprintf('No active RuleVersion for rule "%s" at %s.', RuleId::FRANCHISE_EN_BASE_ELIGIBILITE, $at->format('Y-m-d')),
            );
        }

        $conditions = $franchiseRuleVersion->getConditions();
        $vatStatus = $fiscalContext->getVatStatus();

        /** @var list<string> $outOfScopeVatStatuses */
        $outOfScopeVatStatuses = $conditions['out_of_scope_vat_statuses'];

        if (\in_array($vatStatus->value, $outOfScopeVatStatuses, true)) {
            return new EligibilityDiagnostic(
                $organization,
                $fiscalContext,
                null,
                null,
                (string) $conditions['out_of_scope_explanation'],
                $franchiseRuleVersion,
                $calendarRuleVersion,
            );
        }

        $receptionObligationDate = new \DateTimeImmutable((string) $conditions['reception_obligation_date']);

        $calendarConditions = $calendarRuleVersion->getConditions();
        $emissionDateKey = CompanySizeCategory::PME_TPE_MICRO === $fiscalContext->getCompanySizeCategory()
            ? 'emission_date_pme_tpe_micro'
            : 'emission_date_grande_entreprise_eti';
        $emissionObligationDate = new \DateTimeImmutable((string) $calendarConditions[$emissionDateKey]);

        $explanation = $franchiseRuleVersion->getExplanationTemplate();
        if (VatStatus::ASSUJETTI_FRANCHISE_EN_BASE === $vatStatus) {
            $explanation .= ' '.(string) $conditions['franchise_en_base_note'];
        }

        return new EligibilityDiagnostic(
            $organization,
            $fiscalContext,
            $receptionObligationDate,
            $emissionObligationDate,
            $explanation,
            $franchiseRuleVersion,
            $calendarRuleVersion,
        );
    }
}
