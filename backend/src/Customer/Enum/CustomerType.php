<?php

declare(strict_types=1);

namespace App\Customer\Enum;

/**
 * docs/07-data-model.md, section 8 : détermine le jeu de règles applicable (e-invoicing vs
 * e-reporting, docs/02-regulatory-study.md section 7) - jamais un simple booléen
 * professionnel/particulier, le cas étranger suit un régime distinct des deux autres.
 */
enum CustomerType: string
{
    case PROFESSIONNEL_FRANCAIS = 'PROFESSIONNEL_FRANCAIS';
    case PARTICULIER = 'PARTICULIER';
    case PROFESSIONNEL_ETRANGER = 'PROFESSIONNEL_ETRANGER';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
