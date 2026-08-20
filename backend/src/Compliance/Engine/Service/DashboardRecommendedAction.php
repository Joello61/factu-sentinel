<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Service;

use App\Compliance\Engine\Entity\ComplianceAnalysis;

/**
 * Une action recommandée du Dashboard (docs/08-api-specification.md, section 33) : le
 * message est toujours la correction_action déjà figée d'un ComplianceFinding existant
 * (jamais un texte généré ici) - App\Compliance\Engine\Service\DashboardAggregator reste
 * une vue de lecture pure, jamais un générateur de recommandations.
 */
final class DashboardRecommendedAction
{
    public function __construct(
        public readonly string $message,
        public readonly ComplianceAnalysis $relatedAnalysis,
    ) {
    }
}
