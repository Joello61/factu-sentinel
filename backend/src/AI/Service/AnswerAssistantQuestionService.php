<?php

declare(strict_types=1);

namespace App\AI\Service;

use App\AI\Exception\AiProviderUnavailableException;
use App\AI\Http\AssistantAnswerView;
use App\Identity\Entity\User;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Security\CurrentOrganizationResolver;
use App\Shared\Security\EmailVerificationGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Orchestration de POST /assistant/questions (docs/08-api-specification.md, section 35 ;
 * US-AI-002). Même style que App\AI\Service\ExplainComplianceFindingService : synchrone,
 * appel Mistral hors transaction, un seul enregistrement d'audit après coup. Contrairement à
 * l'endpoint d'explication, aucune ressource à autoriser (la question n'est rattachée à
 * aucune entité) - seules la vérification email et la limite de débit s'appliquent.
 */
final class AnswerAssistantQuestionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AIGateway $aiGateway,
        private readonly AuditLogger $auditLogger,
        private readonly Security $security,
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
        private readonly EmailVerificationGuard $emailVerificationGuard,
        #[Autowire(service: 'limiter.ai_assistant')]
        private readonly RateLimiterFactory $rateLimiter,
    ) {
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function answer(string $question): array
    {
        $this->emailVerificationGuard->assertVerified();

        $limit = $this->rateLimiter->create($this->currentOrganizationResolver->getOrganizationId()->toRfc4122())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        try {
            $answer = $this->aiGateway->answerQuestion($question);
        } catch (AiProviderUnavailableException $exception) {
            $this->audit(\strlen($question), success: false);

            throw new ServiceUnavailableHttpException(null, "L'assistant IA n'est pas disponible pour le moment.", $exception);
        }

        $this->audit(\strlen($question), success: true);

        return ['status' => 200, 'body' => ['data' => AssistantAnswerView::create($question, $answer)]];
    }

    private function audit(int $questionLength, bool $success): void
    {
        $this->auditLogger->record(
            $this->currentOrganizationResolver->getOrganizationId(),
            ActorType::USER,
            $this->currentActorId(),
            EventType::ASSISTANT_QUESTION_ASKED,
            'AssistantQuestion',
            (string) Uuid::v7(),
            null,
            ['success' => $success, 'question_length' => $questionLength],
        );
        $this->entityManager->flush();
    }

    private function currentActorId(): ?Uuid
    {
        $currentUser = $this->security->getUser();

        return $currentUser instanceof User ? $currentUser->getId() : null;
    }
}
