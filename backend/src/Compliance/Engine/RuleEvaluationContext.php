<?php

declare(strict_types=1);

namespace App\Compliance\Engine;

use App\Customer\Entity\Customer;
use App\Invoicing\Entity\Invoice;
use App\Organization\Entity\FiscalContext;

/**
 * Contexte résolu transmis à un RuleChecker (docs/06-technical-architecture.md, section 8 :
 * "sélection des règles à partir du contexte résolu"). Value object immuable, aucune
 * dépendance sur l'heure d'exécution au-delà de $at (déterminisme, section 11 de
 * docs/09-test-strategy.md : jamais new \DateTimeImmutable() dans un RuleChecker ou
 * l'évaluateur, toujours ce $at explicite).
 */
final class RuleEvaluationContext
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly Customer $customer,
        public readonly FiscalContext $fiscalContext,
        public readonly \DateTimeImmutable $at,
    ) {
    }
}
