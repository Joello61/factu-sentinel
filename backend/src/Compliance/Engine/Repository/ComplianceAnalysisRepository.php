<?php

declare(strict_types=1);

namespace App\Compliance\Engine\Repository;

use App\Compliance\Engine\Entity\ComplianceAnalysis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ComplianceAnalysis>
 */
final class ComplianceAnalysisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ComplianceAnalysis::class);
    }

    /**
     * Anciens et nouveaux résultats tous deux consultables (US-COMPLIANCE-006), jamais
     * écrasés : simple liste triée, pas de filtre de statut.
     *
     * @return array{items: list<ComplianceAnalysis>, totalCount: int}
     */
    public function paginateForInvoice(Uuid $invoiceId, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.invoice = :invoiceId')
            ->setParameter('invoiceId', $invoiceId, UuidType::NAME);

        // Le clone doit précéder tout ->orderBy() : PostgreSQL rejette un ORDER BY sur une
        // colonne absente du SELECT en présence d'un COUNT() (ni agrégée, ni groupée).
        $totalCount = (int) (clone $qb)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Tri secondaire sur l'id (Uuid::v7(), ordonné dans le temps) : triggeredAt est
        // stocké en TIMESTAMP(0) (précision à la seconde), insuffisant pour départager deux
        // analyses lancées dans la même seconde -- sans lui, l'ordre "plus récent en premier"
        // ne serait pas garanti stable pour ce cas.
        $items = $qb
            ->orderBy('a.triggeredAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'totalCount' => $totalCount];
    }
}
