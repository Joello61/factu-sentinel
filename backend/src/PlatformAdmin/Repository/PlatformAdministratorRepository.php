<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Repository;

use App\PlatformAdmin\Entity\PlatformAdministrator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Provider dédié au firewall `platform_admin` (backend/config/packages/security.yaml) -
 * jamais partagé avec App\Identity\Repository\UserRepository, même si un email identique
 * existait des deux côtés (ADR-009 : identités structurellement séparées).
 *
 * Le firewall JWT stateless recharge ce provider à chaque requête authentifiée (même
 * comportement que UserRepository pour le firewall tenant, docs/12-roadmap.md bilan Phase 13)
 * - `loadUserByIdentifier()` exclut les comptes révoqués, donc un `revokedAt` positionné en
 * cours de session coupe l'accès dès la requête suivante, jamais seulement à l'expiration du
 * JWT.
 *
 * @extends ServiceEntityRepository<PlatformAdministrator>
 */
final class PlatformAdministratorRepository extends ServiceEntityRepository implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformAdministrator::class);
    }

    public function findOneByEmail(string $email): ?PlatformAdministrator
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.email = :email')
            ->andWhere('p.revokedAt IS NULL')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $administrator = $this->findOneByEmail($identifier);

        if (null === $administrator) {
            throw new UserNotFoundException(sprintf('PlatformAdministrator with email "%s" not found.', $identifier));
        }

        return $administrator;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof PlatformAdministrator) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return PlatformAdministrator::class === $class || is_subclass_of($class, PlatformAdministrator::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof PlatformAdministrator) {
            return;
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }
}
