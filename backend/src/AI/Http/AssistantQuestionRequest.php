<?php

declare(strict_types=1);

namespace App\AI\Http;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /assistant/questions (docs/08-api-specification.md, section 35 ; US-AI-002). Longueur
 * bornée à 500 caractères : une question de compréhension générale, pas un champ de texte
 * libre illimité (coût, cohérent avec docs/06-technical-architecture.md section 15
 * "Monitoring et limitation des coûts").
 */
final readonly class AssistantQuestionRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 500)]
        public string $question,
    ) {
    }
}
