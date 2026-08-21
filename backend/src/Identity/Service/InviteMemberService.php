<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\Invitation;
use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use App\Identity\Mailer\InvitationMailer;
use App\Identity\Repository\InvitationRepository;
use App\Organization\Entity\Organization;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Idempotency\Service\IdempotencyStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * US-TEAM-001 (docs/08-api-specification.md, section 25). Même patron que
 * App\Document\Service\UploadDocumentService pour l'articulation idempotence/transaction/
 * effet de bord externe : l'email d'invitation n'est envoyé qu'après le commit réel de la
 * transaction (jamais depuis l'intérieur de wrapInTransaction()), et jamais renvoyé sur le
 * chemin de relecture idempotente (doInvite() n'est alors jamais appelée).
 */
final readonly class InviteMemberService
{
    private const int EXPIRY_DAYS = 7;

    public function __construct(
        private InvitationRepository $invitationRepository,
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
        private InvitationMailer $invitationMailer,
        private IdempotencyStore $idempotencyStore,
        #[Autowire(service: 'limiter.team_invite')]
        private RateLimiterFactory $rateLimiter,
    ) {
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function invite(Organization $organization, User $invitedBy, string $email, Role $role, string $idempotencyKey): array
    {
        $limit = $this->rateLimiter->create($organization->getId()->toRfc4122())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        // Voir App\Document\Service\UploadDocumentService::upload() pour la raison précise
        // de cette closure englobante en "function" (jamais une arrow function "fn", qui
        // capturerait $dispatch par valeur plutôt que par référence).
        $dispatch = null;

        $result = $this->entityManager->wrapInTransaction(
            function () use ($organization, $invitedBy, $email, $role, $idempotencyKey, &$dispatch): array {
                return $this->idempotencyStore->execute(
                    $organization->getId(),
                    $idempotencyKey,
                    function () use ($organization, $invitedBy, $email, $role, &$dispatch): array {
                        [$body, $dispatch] = $this->doInvite($organization, $invitedBy, $email, $role);

                        return $body;
                    },
                );
            },
        );

        if (null !== $dispatch) {
            $dispatch();
        }

        return $result;
    }

    /** @return array{0: array{status: int, body: array<string, mixed>}, 1: callable(): void} */
    private function doInvite(Organization $organization, User $invitedBy, string $email, Role $role): array
    {
        $existing = $this->invitationRepository->findActivePendingByEmail($email);
        if (null !== $existing) {
            throw new ConflictHttpException('Une invitation est déjà en attente pour cet email dans cette organisation.');
        }

        $selector = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $verifier);
        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d days', self::EXPIRY_DAYS));

        $invitation = new Invitation($organization, $email, $role, $invitedBy, $selector, $tokenHash, $expiresAt);
        $this->entityManager->persist($invitation);

        $this->auditLogger->record(
            $organization->getId(),
            ActorType::USER,
            $invitedBy->getId(),
            EventType::MEMBER_INVITED,
            'Invitation',
            $invitation->getId()->toRfc4122(),
            null,
            ['email' => $email, 'role' => $role->value],
        );

        $plainToken = $selector . $verifier;
        $body = [
            'status' => JsonResponse::HTTP_CREATED,
            'body' => ['data' => self::toView($invitation)],
        ];

        return [$body, function () use ($invitation, $plainToken): void {
            $this->invitationMailer->send($invitation, $plainToken);
        }];
    }

    /** @return array<string, mixed> */
    public static function toView(Invitation $invitation): array
    {
        return [
            'id' => $invitation->getId()->toRfc4122(),
            'email' => $invitation->getEmail(),
            'role' => $invitation->getRole()->value,
            'status' => $invitation->getStatus()->value,
            'created_at' => $invitation->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
