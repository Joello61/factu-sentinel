<?php

declare(strict_types=1);

namespace App\Customer\Http;

use App\Customer\Enum\CustomerType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente le Customer après fusion de la requête PATCH avec l'état existant
 * (App\Customer\Service\CustomerService::update()) : jamais mappé directement depuis la
 * requête HTTP via #[MapRequestPayload], pour la même raison que
 * App\Organization\Http\MergedFiscalContextInput — distinguer "champ absent" de "champ
 * explicitement null" est nécessaire à la sémantique de PATCH partiel
 * (docs/08-api-specification.md, section 26).
 */
final readonly class UpdateCustomerInput
{
    public function __construct(
        #[Assert\NotNull(message: 'Le type de client (customer_type) est requis.')]
        #[Assert\Choice(callback: [CustomerType::class, 'values'], message: 'Type de client invalide.')]
        public ?string $customerType,
        #[Assert\NotBlank(message: 'Le nom du client est requis.')]
        public ?string $name,
        #[Assert\Regex(pattern: '/^\d{9}$/', message: 'Le SIREN doit contenir exactement 9 chiffres.')]
        public ?string $siren,
        public ?string $vatNumber,
        #[Assert\NotBlank(message: 'Le pays (country) est requis.')]
        #[Assert\Country(message: 'Le pays doit être un code ISO 3166-1 alpha-2 valide.')]
        public ?string $country,
    ) {
    }
}
