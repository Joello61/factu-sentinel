<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\Membership;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Membership>
 *
 * Membership implémente TenantScopedInterface : toute méthode ici est déjà bornée à
 * l'organisation courante par TenantFilter (docs/06-technical-architecture.md, ADR-004) -
 * jamais un filtre applicatif redondant/oublié.
 */
final class MembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Membership::class);
    }

    /** @return list<Membership> */
    public function findAllForCurrentOrganization(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
