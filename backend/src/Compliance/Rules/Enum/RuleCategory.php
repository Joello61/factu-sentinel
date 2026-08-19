<?php

declare(strict_types=1);

namespace App\Compliance\Rules\Enum;

/**
 * docs/07-data-model.md, section 15 cite plusieurs catégories possibles (mention_obligatoire,
 * eligibilite, qualification_operation, format, ...) mais seule ELIGIBILITE a une règle
 * réelle au MVP à ce stade (Phase 3) : les autres seront ajoutées en Phase 5 au fil des
 * règles effectivement codées, plutôt qu'anticipées ici sans donnée correspondante
 * (cohérent avec Role, qui n'a qu'OWNER malgré un modèle conceptuel à plusieurs rôles).
 */
enum RuleCategory: string
{
    case ELIGIBILITE = 'ELIGIBILITE';
}
