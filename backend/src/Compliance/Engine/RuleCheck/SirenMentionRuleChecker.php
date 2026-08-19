<?php

declare(strict_types=1);

namespace App\Compliance\Engine\RuleCheck;

use App\Compliance\Engine\RuleEvaluationContext;

/**
 * Règle mention-siren-client (docs/02-regulatory-study.md, section 10 ; scénario REG-004
 * de docs/09-test-strategy.md, section 9).
 *
 * Customer.siren === null produit VIOLATED, jamais DATA_MISSING : l'absence du SIREN EST
 * la vérification elle-même ("le client professionnel français a-t-il un SIREN renseigné
 * sur la facture ?"), pas une donnée manquante qui empêcherait de mener une autre
 * vérification. REG-004 est explicite : "NON_CONFORME sur la vérification SIREN" quand le
 * SIREN est absent, jamais A_VERIFIER. À ne pas confondre avec BR-COMPLIANCE-003, qui
 * s'applique quand une donnée manquante empêche de MENER la vérification, pas quand son
 * absence EST la réponse à la vérification.
 */
final class SirenMentionRuleChecker implements RuleCheckerInterface
{
    public function check(RuleEvaluationContext $context): RuleCheckResult
    {
        $siren = $context->customer->getSiren();

        return new RuleCheckResult(
            null === $siren ? RuleCheckOutcome::VIOLATED : RuleCheckOutcome::SATISFIED,
            'customer.siren',
            $siren,
        );
    }
}
