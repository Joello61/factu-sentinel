<?php

declare(strict_types=1);

namespace App\Invoicing\Repository;

use App\Invoicing\Entity\Invoice;
use App\Invoicing\Enum\InvoiceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
final class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * @return array{items: list<Invoice>, totalCount: int}
     */
    public function paginate(?InvoiceStatus $status, ?Uuid $customerId, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('i');

        if (null !== $status) {
            $qb->andWhere('i.status = :status')->setParameter('status', $status);
        }

        if (null !== $customerId) {
            $qb->andWhere('i.customer = :customerId')->setParameter('customerId', $customerId, UuidType::NAME);
        }

        // Le clone doit précéder tout ->orderBy() : PostgreSQL rejette un ORDER BY sur une
        // colonne absente du SELECT en présence d'un COUNT() (ni agrégée, ni groupée).
        $totalCount = (int) (clone $qb)
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('i.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'totalCount' => $totalCount];
    }
}
