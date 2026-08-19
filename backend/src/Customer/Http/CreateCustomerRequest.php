<?php

declare(strict_types=1);

namespace App\Customer\Http;

use App\Customer\Enum\CustomerType;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /customers (docs/08-api-specification.md, section 26). `#[SerializedName]` requis
 * sur chaque champ multi-mot : aucun NameConverter global n'est configuré dans ce projet
 * (vérifié, backend/config/packages/framework.yaml), donc le Serializer ne convertit jamais
 * automatiquement customer_type/vat_number vers customerType/vatNumber sans cette annotation
 * explicite par champ (vérifié dans le code source de symfony/serializer, MetadataAwareNameConverter
 * lit ces attributs par défaut, sans configuration supplémentaire).
 *
 * `siren` volontairement jamais `#[Assert\NotBlank]`, y compris pour un
 * PROFESSIONNEL_FRANCAIS : son absence n'est pas une erreur de validation (voir plan
 * Phase 4, décision D1 ; docs/05-user-stories.md US-CUSTOMER-002 ; CLAUDE.md racine section
 * 9, BR-COMPLIANCE-003/ADR-002). Seul le Compliance Engine (Phase 5) qualifiera cette
 * absence, jamais ce endpoint.
 *
 * `country` validé par `#[Assert\Country]` (liste ISO 3166-1 alpha-2 réelle, pas un simple
 * format) : nécessite le composant `symfony/intl`, installé (composer require symfony/intl,
 * v8.1.4, aligné sur le reste de la stack Symfony 8.1.*).
 */
final readonly class CreateCustomerRequest
{
    public function __construct(
        #[SerializedName('customer_type')]
        #[Assert\NotNull(message: 'Le type de client (customer_type) est requis.')]
        #[Assert\Choice(callback: [CustomerType::class, 'values'], message: 'Type de client invalide.')]
        public string $customerType,
        #[Assert\NotBlank(message: 'Le nom du client est requis.')]
        public string $name,
        #[Assert\Regex(pattern: '/^\d{9}$/', message: 'Le SIREN doit contenir exactement 9 chiffres.')]
        public ?string $siren = null,
        #[SerializedName('vat_number')]
        public ?string $vatNumber = null,
        #[Assert\NotBlank(message: 'Le pays (country) est requis.')]
        #[Assert\Country(message: 'Le pays doit être un code ISO 3166-1 alpha-2 valide.')]
        public ?string $country = null,
    ) {
    }
}
