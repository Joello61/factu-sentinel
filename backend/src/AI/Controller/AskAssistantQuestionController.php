<?php

declare(strict_types=1);

namespace App\AI\Controller;

use App\AI\Http\AssistantQuestionRequest;
use App\AI\Service\AnswerAssistantQuestionService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /assistant/questions (docs/08-api-specification.md, section 35 ; US-AI-002).
 */
final class AskAssistantQuestionController
{
    public function __construct(
        private readonly AnswerAssistantQuestionService $answerAssistantQuestionService,
    ) {
    }

    #[Route('/api/v1/assistant/questions', name: 'assistant_questions', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] AssistantQuestionRequest $payload): JsonResponse
    {
        $result = $this->answerAssistantQuestionService->answer($payload->question);

        return new JsonResponse($result['body'], $result['status']);
    }
}
