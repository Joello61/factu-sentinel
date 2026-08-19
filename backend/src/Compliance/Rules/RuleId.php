<?php

declare(strict_types=1);

namespace App\Compliance\Rules;

/**
 * Identifiants métier stables des RegulatoryRule de cette phase (docs/06-technical-architecture.md,
 * section 9 : "identifiant stable de la règle, ex. mention-siren-client"). Centralisés ici
 * pour éviter de dupliquer ces chaînes entre la migration de seed et les services qui les
 * consomment (CompanySizeCategoryResolver, EligibilityDiagnosticCalculator).
 *
 * Valeurs alignées sur celles déjà anticipées par la Regulatory Traceability Matrix
 * (docs/09-test-strategy.md, section 52) avant l'implémentation de cette phase : reprises
 * telles quelles plutôt que remplacées par un nom choisi indépendamment.
 */
final class RuleId
{
    public const string FRANCHISE_EN_BASE_ELIGIBILITE = 'franchise-en-base-eligibilite';
    public const string CALENDRIER_OBLIGATION_EMISSION = 'calendrier-obligation-emission';

    private function __construct()
    {
    }
}
