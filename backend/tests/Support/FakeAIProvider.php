<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\AI\Exception\AiProviderUnavailableException;
use App\AI\Service\AIProviderInterface;

/**
 * Double de test contrôlable pour AIProviderInterface - jamais d'appel HTTP réel vers
 * Mistral dans les tests (backend/CLAUDE.md, section 13). Enregistré à la place du
 * App\AI\Service\MistralProvider réel uniquement en environnement de test
 * (backend/config/services.yaml, bloc when@test), même principe que
 * FakeStructuredDocumentValidator (backend/tests/Integration/Document/
 * ExtractDocumentContentHandlerTest.php) mais câblé dans le conteneur : les tests
 * fonctionnels de App\AI\Controller\* passent par le vrai routage HTTP, donc par le
 * conteneur réel, contrairement à ExtractDocumentContentHandlerTest qui instancie son
 * handler directement.
 */
final class FakeAIProvider implements AIProviderInterface
{
    public bool $shouldFail = false;

    /** Dernier prompt système/utilisateur transmis, pour les assertions de contenu. */
    public ?string $lastSystemPrompt = null;
    public ?string $lastUserPrompt = null;

    public function complete(string $systemPrompt, string $userPrompt, float $timeoutSeconds): string
    {
        $this->lastSystemPrompt = $systemPrompt;
        $this->lastUserPrompt = $userPrompt;

        if ($this->shouldFail) {
            throw new AiProviderUnavailableException('Fake provider configured to fail.');
        }

        return 'Réponse générée par le double de test.';
    }
}
