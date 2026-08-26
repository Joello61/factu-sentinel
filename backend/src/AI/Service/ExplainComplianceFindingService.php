<?php

declare(strict_types=1);

namespace App\AI\Service;

use App\AI\Entity\AiCallLogEntry;
use App\AI\Enum\AiCallEndpoint;
use App\AI\Exception\AiProviderUnavailableException;
use App\AI\Http\ExplanationView;
use App\Compliance\Engine\Entity\ComplianceFinding;
use App\Compliance\Engine\Repository\ComplianceFindingRepository;
use App\Identity\Entity\User;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Security\CurrentOrganizationResolver;
use App\Shared\Security\EmailVerificationGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Orchestration de POST /compliance-findings/{id}/explanations (docs/08-api-specification.md,
 * section 35 ; US-AI-001). Toujours synchrone (plan Phase 8, section "Synchronous, not
 * async") : un seul appel HTTP borné vers Mistral dans le cycle requête/réponse, jamais de
 * Messenger. L'appel au fournisseur IA se fait délibérément HORS de toute transaction
 * Doctrine (contrairement à App\Compliance\Engine\Service\RunComplianceAnalysisService) :
 * il n'y a ici aucune écriture de domaine à coordonner atomiquement, une seule ligne
 * d'audit après coup - ouvrir une transaction autour d'un appel réseau externe serait une
 * mauvaise pratique sans aucun bénéfice ici.
 */
final class ExplainComplianceFindingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ComplianceFindingRepository $complianceFindingRepository,
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
    public function explain(string $findingId): array
    {
        $finding = $this->resolveFinding($findingId);

        $this->emailVerificationGuard->assertVerified();

        $limit = $this->rateLimiter->create($this->currentOrganizationResolver->getOrganizationId()->toRfc4122())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $context = $this->buildContext($finding);

        try {
            $explanation = $this->aiGateway->explainFinding($context);
        } catch (AiProviderUnavailableException $exception) {
            $this->audit($finding, success: false);

            throw new ServiceUnavailableHttpException(null, "L'assistant IA n'est pas disponible pour le moment.", $exception);
        }

        $this->audit($finding, success: true);

        return ['status' => 200, 'body' => ['data' => ExplanationView::create($finding->getId()->toRfc4122(), $explanation)]];
    }

    private function resolveFinding(string $findingId): ComplianceFinding
    {
        if (!Uuid::isValid($findingId)) {
            throw new NotFoundHttpException("Ce résultat de conformité n'existe pas ou n'est plus disponible.");
        }

        $finding = $this->complianceFindingRepository->findByIdWithTenantCheck(Uuid::fromString($findingId));
        if (null === $finding) {
            throw new NotFoundHttpException("Ce résultat de conformité n'existe pas ou n'est plus disponible.");
        }

        return $finding;
    }

    private function buildContext(ComplianceFinding $finding): ComplianceFindingExplanationContext
    {
        $ruleVersion = $finding->getRuleVersion();
        $rule = $ruleVersion->getRule();

        return new ComplianceFindingExplanationContext(
            ruleId: $rule->getId(),
            ruleName: $rule->getName(),
            ruleDescription: $rule->getDescription(),
            ruleVersionNumber: $ruleVersion->getVersionNumber(),
            result: $finding->getResult()->value,
            message: $finding->getMessage(),
            relatedField: $finding->getRelatedField(),
            observedValue: $finding->getObservedValue(),
            correctionAction: $finding->getCorrectionAction(),
            sourceReference: $ruleVersion->getSourceReference(),
            confidenceLevel: $ruleVersion->getConfidenceLevel()->value,
        );
    }

    private function audit(ComplianceFinding $finding, bool $success): void
    {
        $organizationId = $this->currentOrganizationResolver->getOrganizationId();

        $this->auditLogger->record(
            $organizationId,
            ActorType::USER,
            $this->currentActorId(),
            EventType::COMPLIANCE_FINDING_EXPLAINED,
            'ComplianceFinding',
            $finding->getId()->toRfc4122(),
            null,
            ['success' => $success],
        );

        // Plan Phase 15 (US-PLATFORMADMIN-005, US-ANALYTICS-001) - volume/coût des appels IA
        // désormais persisté. estimated_cost reste null : aucun calcul de coût réel n'existe
        // encore côté App\AI\Service\MistralProvider, jamais une valeur inventée ici.
        $this->entityManager->persist(new AiCallLogEntry($organizationId, AiCallEndpoint::EXPLANATION, $success, null));

        $this->entityManager->flush();
    }

    private function currentActorId(): ?Uuid
    {
        $currentUser = $this->security->getUser();

        return $currentUser instanceof User ? $currentUser->getId() : null;
    }
}
