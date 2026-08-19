<?php

declare(strict_types=1);

namespace App\Organization\Repository;

use App\Organization\Entity\FiscalContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<FiscalContext>
 */
final class FiscalContextRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FiscalContext::class);
    }

    /**
     * Version courante (effective_until IS NULL) : au plus une par organisation, garantie
     * par l'index partiel de la migration (voir FiscalContext).
     */
    public function findCurrent(Uuid $organizationId): ?FiscalContext
    {
        return $this->createQueryBuilder('fc')
            ->andWhere('fc.organization = :organizationId')
            ->andWhere('fc.effectiveUntil IS NULL')
            ->setParameter('organizationId', $organizationId, UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
