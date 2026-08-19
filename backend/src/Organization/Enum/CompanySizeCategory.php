<?php

declare(strict_types=1);

namespace App\Organization\Enum;

/**
 * docs/07-data-model.md, section 7 : classification binaire propre au calendrier de la
 * réforme (docs/02-regulatory-study.md, section 5, qui regroupe GE et ETI sous une même
 * date d'émission, et PME/TPE/micro sous une autre), dérivée de la seule distinction
 * PME/non-PME au sens INSEE. Ce n'est PAS la classification légale INSEE à quatre niveaux
 * (Micro/PME/ETI/GE) : GRANDE_ENTREPRISE_ETI signifie seulement "non-PME au sens INSEE",
 * jamais "confirmé ETI" ou "confirmé grande entreprise" : le produit n'a pas besoin de les
 * distinguer, les deux dates d'émission étant identiques (voir
 * Compliance/Rules, RuleVersion "calendrier-obligation-emission").
 */
enum CompanySizeCategory: string
{
    case GRANDE_ENTREPRISE_ETI = 'GRANDE_ENTREPRISE_ETI';
    case PME_TPE_MICRO = 'PME_TPE_MICRO';
}
