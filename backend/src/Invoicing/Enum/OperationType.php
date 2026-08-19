<?php

declare(strict_types=1);

namespace App\Invoicing\Enum;

/**
 * Nouvelle mention obligatoire à vérifier (docs/07-data-model.md, section 10 ;
 * docs/02-regulatory-study.md, section 10).
 */
enum OperationType: string
{
    case VENTE_BIEN = 'VENTE_BIEN';
    case PRESTATION_SERVICE = 'PRESTATION_SERVICE';
    case MIXTE = 'MIXTE';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
