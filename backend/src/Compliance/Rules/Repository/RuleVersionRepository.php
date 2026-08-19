<?php

declare(strict_types=1);

namespace App\Compliance\Rules\Repository;

use App\Compliance\Rules\Entity\RuleVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RuleVersion>
 */
final class RuleVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuleVersion::class);
    }

    /**
     * Version active d'une règle à une date donnée (docs/06-technical-architecture.md,
     * section 10 : la sélection se fait toujours à la date de l'analyse, jamais à la date
     * de consultation ultérieure). Au plus une version doit satisfaire cet intervalle par
     * construction (pas de chevauchement, docs/07-data-model.md section 37) ; sinon
     * getSingleResult() échoue plutôt que de choisir silencieusement une version.
     */
    public function findActive(string $ruleId, \DateTimeImmutable $at): ?RuleVersion
    {
        return $this->createQueryBuilder('rv')
            ->andWhere('rv.rule = :ruleId')
            ->andWhere('rv.effectiveFrom <= :at')
            ->andWhere('rv.effectiveUntil IS NULL OR rv.effectiveUntil > :at')
            ->setParameter('ruleId', $ruleId)
            ->setParameter('at', $at, 'date_immutable')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
