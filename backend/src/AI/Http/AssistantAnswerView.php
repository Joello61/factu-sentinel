<?php

declare(strict_types=1);

namespace App\AI\Http;

/**
 * docs/08-api-specification.md, section 35 (contrat POST /assistant/questions, complété en
 * Phase 8 - non spécifié en détail avant). "source" reprend le même style que
 * App\AI\Http\ExplanationView, en nommant explicitement l'étude réglementaire plutôt que le
 * fournisseur IA : elle reste la seule source citée, jamais Mistral lui-même.
 */
final class AssistantAnswerView
{
    private const string SOURCE = "Généré par assistance IA à partir de l'étude réglementaire du produit (02-regulatory-study.md)";

    /** @return array<string, mixed> */
    public static function create(string $question, string $answer): array
    {
        return [
            'question' => $question,
            'answer' => $answer,
            'source' => self::SOURCE,
        ];
    }
}
