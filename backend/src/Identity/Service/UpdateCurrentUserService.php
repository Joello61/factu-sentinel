<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\RefreshToken;
use App\Identity\Entity\User;
use App\Identity\Http\UpdateUserRequest;
use App\Identity\Mailer\VerifyEmailMailer;
use App\Identity\Repository\UserRepository;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository as GesdinetRefreshTokenRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Orchestration transactionnelle de PATCH /users/current (US-SETTINGS-001, voir plan Phase
 * 13), même style que App\Customer\Service\CustomerService.
 */
final class UpdateCurrentUserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogger $auditLogger,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly VerifyEmailMailer $verifyEmailMailer,
    ) {
    }

    public function update(User $user, UpdateUserRequest $request): User
    {
        return $this->entityManager->wrapInTransaction(function () use ($user, $request): User {
            $wantsEmailChange = null !== $request->email && $request->email !== $user->getEmail();
            $wantsPasswordChange = null !== $request->newPassword;

            if (!$wantsEmailChange && !$wantsPasswordChange) {
                throw new ValidationFailedException(
                    $request,
                    $this->oneViolation($request, 'email', 'Aucune modification demandée : renseignez un nouvel email ou un nouveau mot de passe.'),
                );
            }

            if (null === $request->currentPassword) {
                throw new ValidationFailedException(
                    $request,
                    $this->oneViolation($request, 'current_password', 'Le mot de passe actuel est requis pour confirmer cette modification.'),
                );
            }

            if (!$this->passwordHasher->isPasswordValid($user, $request->currentPassword)) {
                throw new ValidationFailedException(
                    $request,
                    $this->oneViolation($request, 'current_password', 'Mot de passe actuel incorrect.'),
                );
            }

            $previousState = $this->snapshot($user, false);
            $passwordChanged = false;

            if ($wantsEmailChange) {
                $existing = $this->userRepository->findOneByEmail($request->email);
                if (null !== $existing && !$existing->getId()->equals($user->getId())) {
                    throw new ConflictHttpException('Un compte existe déjà avec cet email.');
                }

                $user->setEmail($request->email);
                $user->markEmailAsUnverified();
            }

            if ($wantsPasswordChange) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $request->newPassword));
                $this->refreshTokenRepository()->deleteByUser($user);
                $passwordChanged = true;
            }

            $this->auditLogger->record(
                $this->organizationId($user),
                ActorType::USER,
                $user->getId(),
                EventType::USER_UPDATED,
                'User',
                $user->getId()->toRfc4122(),
                $previousState,
                $this->snapshot($user, $passwordChanged),
            );

            if ($wantsEmailChange) {
                $this->verifyEmailMailer->send($user);
            }

            return $user;
        });
    }

    /**
     * Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository étend EntityRepository
     * (pas ServiceEntityRepository) : non auto-enregistré comme service par le bundle
     * Doctrine, donc non injectable directement (constaté à l'implémentation, voir plan
     * Phase 13) - obtenu via l'EntityManager, qui résout toujours repositoryClass depuis les
     * métadonnées de App\Identity\Entity\RefreshToken quel que soit son mode d'enregistrement.
     */
    private function refreshTokenRepository(): GesdinetRefreshTokenRepository
    {
        $repository = $this->entityManager->getRepository(RefreshToken::class);
        \assert($repository instanceof GesdinetRefreshTokenRepository);

        return $repository;
    }

    private function organizationId(User $user): ?Uuid
    {
        $membership = $user->getMemberships()->first();

        return false !== $membership ? $membership->getOrganization()->getId() : null;
    }

    private function oneViolation(object $root, string $field, string $message): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation($message, null, [], $root, $field, null));

        return $violations;
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user, bool $passwordChanged): array
    {
        return [
            'email' => $user->getEmail(),
            'password_changed' => $passwordChanged,
        ];
    }
}
