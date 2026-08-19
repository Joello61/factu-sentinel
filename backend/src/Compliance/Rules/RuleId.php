<?php

declare(strict_types=1);

namespace App\Compliance\Rules;

/**
 * Identifiants métier stables des RegulatoryRule de cette phase (docs/06-technical-architecture.md,
 * section 9 : "identifiant stable de la règle, ex. mention-siren-client"). Centralisés ici
 * pour éviter de dupliquer ces chaînes entre la migration de seed et les services qui les
 * consomment (CompanySizeCategoryResolver, EligibilityDiagnosticCalculator).
 */
final class RuleId
{
    public const string ELIGIBILITE_FRANCHISE_EN_BASE = 'eligibilite-franchise-en-base';
    public const string ELIGIBILITE_CALENDRIER_TAILLE = 'eligibilite-calendrier-taille';

    private function __construct()
    {
    }
}
