<?php

declare(strict_types=1);

namespace App\Organization\Http;

use App\Organization\Enum\VatStatus;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente le FiscalContext après fusion de la requête PATCH avec le contexte existant
 * (App\Organization\Service\ConfigureOrganizationService) — jamais mappé directement depuis
 * la requête HTTP (contrairement à App\Identity\Http\RegisterRequest) : #[MapRequestPayload]
 * ne permet pas de distinguer "champ absent" de "champ explicitement null" nécessaire à la
 * sémantique de PATCH partiel de cet endpoint (docs/08-api-specification.md, section 24,
 * corrigée par le plan Phase 3, gap 1). Les trois champs bruts sont requis ensemble pour
 * que company_size_category soit calculable (US-COMPANY-002) : la validation ci-dessous
 * porte sur l'état fusionné, jamais sur la requête brute seule.
 */
final readonly class MergedFiscalContextInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le statut TVA (vat_status) est requis.')]
        #[Assert\Choice(callback: [VatStatus::class, 'values'], message: 'Statut TVA invalide.')]
        public ?string $vatStatus,
        #[Assert\NotNull(message: "L'effectif salarié (employees_count) est requis pour déterminer la catégorie de taille.")]
        #[Assert\Type(type: 'int', message: "L'effectif salarié doit être un nombre entier.")]
        #[Assert\PositiveOrZero(message: "L'effectif salarié doit être positif ou nul.")]
        public int|string|null $employeesCount,
        #[Assert\NotBlank(message: "Le chiffre d'affaires annuel (annual_turnover) est requis pour déterminer la catégorie de taille.")]
        #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: "Le chiffre d'affaires annuel doit être un montant décimal valide.")]
        public ?string $annualTurnover,
        #[Assert\NotBlank(message: 'Le total du bilan annuel (annual_balance_sheet_total) est requis pour déterminer la catégorie de taille.')]
        #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Le total du bilan annuel doit être un montant décimal valide.')]
        public ?string $annualBalanceSheetTotal,
    ) {
    }
}
