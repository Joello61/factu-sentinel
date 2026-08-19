<?php

declare(strict_types=1);

namespace App\Invoicing\Http;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une ligne saisie dans le payload de POST/PATCH /invoices (docs/08-api-specification.md,
 * section 19 : les lignes n'ont pas de cycle de vie/endpoint indépendant). Construite
 * manuellement (App\Invoicing\Http\CreateInvoiceRequest::fromArray), jamais via
 * #[MapRequestPayload] : le PropertyInfo de ce projet n'a ni phpdocumentor/reflection-docblock
 * ni phpstan/phpdoc-parser installés (vérifié dans backend/vendor avant d'écrire ce code), donc
 * le Serializer ne peut pas résoudre le type d'élément d'un tableau de DTOs à partir d'une
 * simple propriété `array` - seule la validation (#[Assert\Valid] sur CreateInvoiceRequest,
 * qui parcourt les éléments à l'exécution indépendamment de tout typage déclaré) recoupe
 * ensuite ces objets.
 *
 * `line_amount_ht`/`line_amount_vat`/`line_amount_ttc` ne sont volontairement pas des champs
 * de ce DTO : l'exemple de requête de docs/08-api-specification.md section 27 ne les inclut
 * jamais, ils sont toujours calculés côté serveur (App\Invoicing\Service\InvoiceAmountCalculator),
 * jamais acceptés depuis le client.
 */
final readonly class InvoiceLineInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'La description de la ligne est requise.')]
        public string $description,
        #[Assert\Regex(pattern: '/^\d+(\.\d{1,3})?$/', message: 'La quantité doit être un nombre décimal valide.')]
        #[Assert\Positive(message: 'La quantité doit être strictement positive.')]
        public string $quantity,
        #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Le prix unitaire HT doit être un montant décimal valide.')]
        #[Assert\PositiveOrZero(message: 'Le prix unitaire HT doit être positif ou nul.')]
        public string $unitPriceHt,
        #[Assert\Regex(pattern: '/^(0(\.\d{1,4})?|1(\.0{1,4})?)$/', message: 'Le taux de TVA doit être une fraction décimale entre 0 et 1.')]
        public string $vatRate,
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        return new self(
            \is_string($raw['description'] ?? null) ? $raw['description'] : '',
            self::stringify($raw['quantity'] ?? null),
            self::stringify($raw['unit_price_ht'] ?? null),
            self::stringify($raw['vat_rate'] ?? null),
        );
    }

    private static function stringify(mixed $value): string
    {
        return \is_int($value) || \is_float($value) || \is_string($value) ? (string) $value : '';
    }
}
