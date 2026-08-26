<?php

declare(strict_types=1);

namespace App\Tests\Functional\AI;

use App\Identity\Entity\User;
use App\Shared\Audit\Entity\AuditLogEntry;
use App\Shared\Audit\Enum\EventType;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeAIProvider;
use Doctrine\ORM\EntityManagerInterface;

/**
 * POST /assistant/questions (docs/08-api-specification.md, section 35 ; US-AI-002).
 */
final class AskAssistantQuestionControllerTest extends ApiTestCase
{
    private function fakeProvider(): FakeAIProvider
    {
        return static::getContainer()->get(FakeAIProvider::class);
    }

    public function testEmailNotVerifiedReturns403(): void
    {
        $client = $this->createAuthenticatedClient('ai-question-001@example.test');

        $client->jsonRequest('POST', '/api/v1/assistant/questions', ['question' => 'Qu\'est-ce qu\'un SIREN ?']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('EMAIL_VERIFICATION_REQUIRED', $this->jsonBody($client)['error']['code']);
    }

    public function testEmptyQuestionReturns422(): void
    {
        $client = $this->createAuthenticatedClient('ai-question-002@example.test');
        $this->markEmailVerified('ai-question-002@example.test');

        $client->jsonRequest('POST', '/api/v1/assistant/questions', ['question' => '']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testTooLongQuestionReturns422(): void
    {
        $client = $this->createAuthenticatedClient('ai-question-003@example.test');
        $this->markEmailVerified('ai-question-003@example.test');

        $client->jsonRequest('POST', '/api/v1/assistant/questions', ['question' => str_repeat('a', 501)]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testSuccessReturnsAnswerWithFixedSource(): void
    {
        $client = $this->createAuthenticatedClient('ai-question-004@example.test');
        $this->markEmailVerified('ai-question-004@example.test');

        $client->jsonRequest('POST', '/api/v1/assistant/questions', ['question' => 'Qu\'est-ce qu\'un SIREN ?']);

        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];
        self::assertSame('Qu\'est-ce qu\'un SIREN ?', $data['question']);
        self::assertNotEmpty($data['answer']);
        self::assertSame("Généré par assistance IA à partir de l'étude réglementaire du produit (02-regulatory-study.md)", $data['source']);
    }

    public function testProviderFailureReturns503AndAuditsWithoutQuestionText(): void
    {
        $client = $this->createAuthenticatedClient('ai-question-005@example.test');
        $this->markEmailVerified('ai-question-005@example.test');

        $client->disableReboot();
        $this->fakeProvider()->shouldFail = true;

        $question = 'Qu\'est-ce qu\'un SIREN ?';
        $client->jsonRequest('POST', '/api/v1/assistant/questions', ['question' => $question]);

        self::assertResponseStatusCodeSame(503);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // Filtré par eventType ET actorId (contrairement au test équivalent de
        // ExplainComplianceFindingControllerTest, aucun entityId stable n'existe ici pour un
        // ASSISTANT_QUESTION_ASKED - App\AI\Service\AnswerAssistantQuestionService en génère
        // un synthétique par appel). Le seul actorId filtre déjà cette requête à ce test (un
        // utilisateur distinct par test de cette classe) - nécessaire, pas seulement une
        // précaution : "occurred_at" est un TIMESTAMP(0) (précision à la seconde), donc
        // "ORDER BY occurredAt DESC LIMIT 1" seul peut renvoyer l'entrée d'un AUTRE test de
        // cette classe en cas d'égalité sur un environnement d'exécution rapide (constaté en
        // CI : un autre test de cette classe s'exécutant dans la même seconde faisait échouer
        // celui-ci de façon non déterministe).
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'ai-question-005@example.test']);
        self::assertNotNull($user);
        $entries = $em->getRepository(AuditLogEntry::class)->findBy(
            ['eventType' => EventType::ASSISTANT_QUESTION_ASKED, 'actorId' => $user->getId()],
            ['occurredAt' => 'DESC'],
            1,
        );

        self::assertCount(1, $entries);
        $newState = $entries[0]->getNewState();
        self::assertSame(['success' => false, 'question_length' => \strlen($question)], $newState);
        self::assertStringNotContainsString($question, json_encode($newState), 'newState ne doit jamais porter la question brute.');
    }

    public function testRateLimitReturns429AfterExhaustingLimiter(): void
    {
        $client = $this->createAuthenticatedClient('ai-question-006@example.test');
        $this->markEmailVerified('ai-question-006@example.test');

        // config/packages/rate_limiter.yaml: ai_assistant = 30/heure, partagé avec
        // /compliance-findings/{id}/explanations (même budget par organisation).
        for ($i = 0; $i < 30; ++$i) {
            $client->jsonRequest('POST', '/api/v1/assistant/questions', ['question' => 'Qu\'est-ce qu\'un SIREN ?']);
            self::assertResponseStatusCodeSame(200);
        }

        $client->jsonRequest('POST', '/api/v1/assistant/questions', ['question' => 'Qu\'est-ce qu\'un SIREN ?']);

        self::assertResponseStatusCodeSame(429);
    }
}
