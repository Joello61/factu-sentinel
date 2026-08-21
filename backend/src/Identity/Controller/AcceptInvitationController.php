<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\Membership;
use App\Identity\Entity\User;
use App\Identity\Service\InvitationTokenResolver;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /invitations/{token}/accept (authentifié, plan Phase 14 - "Décisions à valider" #3).
 * Verrou de sécurité central de ce endpoint : l'email du compte authentifié doit
 * correspondre exactement à Invitation.email, sinon 403 - jamais un rattachement silencieux
 * à un autre compte que celui réellement invité (revue utilisateur du 21/08/2026).
 *
 * Limité par IP (`limiter.invitation_token_access`, revue de complétude Phase 14, même
 * compteur que GET /invitations/{token}) : l'appelant n'appartient par définition pas encore
 * à l'organisation cible, donc organization_id ne peut jamais servir de clé ici.
 */
final class AcceptInvitationController
{
    public function __construct(
        private readonly InvitationTokenResolver $invitationTokenResolver,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly AuditLogger $auditLogger,
        #[Autowire(service: 'limiter.invitation_token_access')]
        private readonly RateLimiterFactory $rateLimiter,
    ) {
    }

    #[Route('/api/v1/invitations/{token}/accept', name: 'invitations_accept', methods: ['POST'])]
    public function __invoke(string $token, Request $request): JsonResponse
    {
        $limit = $this->rateLimiter->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $invitation = $this->invitationTokenResolver->resolve($token);
        if (null === $invitation) {
            throw new NotFoundHttpException('Cette invitation n\'existe pas ou n\'est plus disponible.');
        }

        if (!$invitation->isPending()) {
            // Authentifié : contrairement à GET /invitations/{token} (public), une
            // distinction explicite ici n'expose rien qu'un jeton déjà en main ne révèle
            // pas déjà.
            throw new ConflictHttpException($invitation->isExpired()
                ? 'Cette invitation a expiré.'
                : 'Cette invitation n\'est plus valide (déjà acceptée ou révoquée).');
        }

        $user = $this->security->getUser();
        \assert($user instanceof User);

        if (0 !== strcasecmp($user->getEmail(), $invitation->getEmail())) {
            throw new AccessDeniedHttpException('Cette invitation a été envoyée à une autre adresse email.');
        }

        foreach ($user->getMemberships() as $existingMembership) {
            if ($existingMembership->getOrganizationId()->equals($invitation->getOrganizationId())) {
                throw new ConflictHttpException('Vous êtes déjà membre de cette organisation.');
            }
        }

        $membership = new Membership($user, $invitation->getOrganization(), $invitation->getRole());
        $this->entityManager->persist($membership);

        $invitation->markAccepted();

        $this->auditLogger->record(
            $invitation->getOrganizationId(),
            ActorType::USER,
            $user->getId(),
            EventType::MEMBER_INVITATION_ACCEPTED,
            'Invitation',
            $invitation->getId()->toRfc4122(),
            null,
            ['membership_id' => $membership->getId()->toRfc4122()],
        );

        $this->entityManager->flush();

        return new JsonResponse([
            'data' => [
                'organization_id' => $invitation->getOrganizationId()->toRfc4122(),
                'role' => $invitation->getRole()->value,
            ],
        ], JsonResponse::HTTP_CREATED);
    }
}
