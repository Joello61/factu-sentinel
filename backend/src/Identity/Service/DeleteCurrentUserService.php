<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\RefreshToken;
use App\Identity\Entity\User;
use App\Identity\Http\DeleteUserRequest;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository as GesdinetRefreshTokenRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Orchestration transactionnelle de DELETE /users/current (US-SETTINGS-002, voir plan Phase
 * 13). Soft delete uniquement (docs/07-data-model.md, section 30) - l'Organization et ses
 * données (Customer, Invoice, ...) ne sont jamais touchées ici : aucun mécanisme de soft
 * delete n'existe pour Organization, et rien dans docs/10-security-privacy.md sections 38-39
 * n'exige une suppression en cascade - la "perte d'accès" décrite y est déjà atteinte par le
 * soft delete du User, seul membre de son Organization à ce stade du produit (avant la
 * multi-appartenance de la Phase 14).
 */
final class DeleteCurrentUserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogger $auditLogger,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function delete(User $user, DeleteUserRequest $request): void
    {
        $this->entityManager->wrapInTransaction(function () use ($user, $request): void {
            if (!$this->passwordHasher->isPasswordValid($user, $request->currentPassword)) {
                throw new ValidationFailedException(
                    $request,
                    $this->oneViolation($request, 'current_password', 'Mot de passe actuel incorrect.'),
                );
            }

            $user->markDeleted(new \DateTimeImmutable());
            $this->refreshTokenRepository()->deleteByUser($user);

            $this->auditLogger->record(
                $this->organizationId($user),
                ActorType::USER,
                $user->getId(),
                EventType::USER_DELETED,
                'User',
                $user->getId()->toRfc4122(),
                null,
                null,
            );
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
}
