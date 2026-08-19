<?php

declare(strict_types=1);

namespace App\Customer\Http;

use App\Customer\Entity\Customer;

/**
 * Forme JSON partagée par les quatre controllers Customer (Create/Get/List/Update) : évite
 * de dupliquer la liste de champs quatre fois (docs/08-api-specification.md, section 26).
 */
final class CustomerView
{
    /** @return array<string, mixed> */
    public static function fromEntity(Customer $customer): array
    {
        return [
            'id' => $customer->getId()->toRfc4122(),
            'customer_type' => $customer->getCustomerType()->value,
            'name' => $customer->getName(),
            'siren' => $customer->getSiren(),
            'vat_number' => $customer->getVatNumber(),
            'country' => $customer->getCountry(),
            'created_at' => $customer->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $customer->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
