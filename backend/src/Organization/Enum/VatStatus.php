<?php

declare(strict_types=1);

namespace App\Organization\Enum;

/**
 * docs/07-data-model.md, section 7 : trois valeurs, pas un simple booléen, pour
 * représenter fidèlement le cas de la franchise en base (assujetti mais non redevable,
 * docs/02-regulatory-study.md section 6) sans le confondre avec une entreprise réellement
 * hors du champ de la TVA.
 */
enum VatStatus: string
{
    case ASSUJETTI_REDEVABLE = 'ASSUJETTI_REDEVABLE';
    case ASSUJETTI_FRANCHISE_EN_BASE = 'ASSUJETTI_FRANCHISE_EN_BASE';
    case NON_ASSUJETTI = 'NON_ASSUJETTI';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
