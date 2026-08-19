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

    /**
     * Ajoutés en Phase 5 (Compliance Engine, docs/12-roadmap.md Phase 5). Couvrent
     * seulement 2 des 4 nouvelles mentions obligatoires de docs/02-regulatory-study.md
     * section 10 (SIREN client, catégorie d'opération) : option paiement TVA sur les
     * débits et adresse de livraison restent hors périmètre de cette phase (voir plan
     * Phase 5). FORMAT_DOCUMENT_STRUCTURE est seedée mais n'a pas de RuleChecker dédié en
     * Phase 5 : son applicability (sources: DOCUMENT_IMPORTE) ne peut jamais être
     * satisfaite avant la Phase 5 (Invoice.source vaut toujours SAISIE_MANUELLE, Document
     * n'existe pas), donc toute évaluation en Phase 5 s'arrête à NON_APPLICABLE.
     */
    public const string MENTION_SIREN_CLIENT = 'mention-siren-client';
    public const string MENTION_CATEGORIE_OPERATION = 'mention-categorie-operation';
    public const string FORMAT_DOCUMENT_STRUCTURE = 'format-document-structure';

    private function __construct()
    {
    }
}
