<?php

declare(strict_types=1);

namespace App\Customer\Repository;

use App\Customer\Entity\Customer;
use App\Customer\Enum\CustomerType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Customer>
 */
final class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /**
     * Un client soft-deleted n'est jamais résolvable via cette méthode (docs/07-data-model.md,
     * section 30) : ni pour GET /customers/{id}, ni comme customer_id référencé par une
     * nouvelle Invoice (docs/08-api-specification.md, section 27). TenantFilter s'applique
     * déjà automatiquement (organization_id), inutile de le répéter ici.
     */
    public function findActive(Uuid $id): ?Customer
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('id', $id, UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array{items: list<Customer>, totalCount: int}
     */
    public function paginate(?CustomerType $customerType, ?string $search, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.deletedAt IS NULL');

        if (null !== $customerType) {
            $qb->andWhere('c.customerType = :customerType')->setParameter('customerType', $customerType);
        }

        if (null !== $search && '' !== $search) {
            $qb->andWhere('c.name LIKE :search OR c.siren LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        // Le clone doit précéder tout ->orderBy() : PostgreSQL rejette un ORDER BY sur une
        // colonne absente du SELECT en présence d'un COUNT() (ni agrégée, ni groupée).
        $totalCount = (int) (clone $qb)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'totalCount' => $totalCount];
    }
}
