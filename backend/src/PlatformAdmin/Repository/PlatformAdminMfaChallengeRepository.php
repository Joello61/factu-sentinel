<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Repository;

use App\PlatformAdmin\Entity\PlatformAdminMfaChallenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformAdminMfaChallenge>
 */
final class PlatformAdminMfaChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformAdminMfaChallenge::class);
    }

    /** Recherche par sélecteur uniquement - jamais le vérificateur en clair (même discipline que App\Identity\Repository\InvitationRepository::findOneBySelector). */
    public function findOneBySelector(string $selector): ?PlatformAdminMfaChallenge
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.tokenSelector = :selector')
            ->setParameter('selector', $selector)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
