<?php

declare(strict_types=1);

namespace App\Organization\Repository;

use App\Organization\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organization>
 */
final class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    /**
     * GET /platform-admin/organizations (docs/08-api-specification.md, section 38.2 ;
     * US-PLATFORMADMIN-001). `Organization` n'implémente jamais `TenantScopedInterface`
     * (c'est le tenant racine, section 25 data-model) - cette lecture cross-tenant n'a donc
     * jamais besoin de suspendre `tenant_filter` (contrairement à
     * App\Identity\Repository\InvitationRepository::findOneBySelector()) : ce filtre ne
     * s'applique de toute façon jamais à cette entité, filtre actif ou non.
     *
     * @return array{items: list<Organization>, totalCount: int}
     */
    public function paginate(?bool $suspended, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('o');

        if (null !== $suspended) {
            $qb->andWhere($suspended ? 'o.suspendedAt IS NOT NULL' : 'o.suspendedAt IS NULL');
        }

        // Le clone doit précéder tout ->orderBy() : PostgreSQL rejette un ORDER BY sur une
        // colonne absente du SELECT en présence d'un COUNT() (ni agrégée, ni groupée).
        $totalCount = (int) (clone $qb)
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('o.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'totalCount' => $totalCount];
    }
}
