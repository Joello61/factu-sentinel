<?php

declare(strict_types=1);

namespace App\Organization\Repository;

use App\Organization\Entity\FiscalContext;
use App\Organization\Enum\CompanySizeCategory;
use App\Organization\Enum\VatStatus;
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

    /**
     * Résolution de segment cross-tenant explicite (plan Phase 15,
     * App\PlatformAdmin\Service\PlatformNotificationRecipientResolver, US-PLATFORMADMIN-004) -
     * critères repris de FiscalContext (docs/07-data-model.md, section 21 : "jamais un champ
     * dupliqué"), toujours sur le contexte fiscal **courant** de chaque organisation
     * (effective_until IS NULL). `tenant_filter` est suspendu le temps de cette unique
     * requête (même patron que App\Identity\Repository\InvitationRepository::findOneBySelector())
     * - défense en profondeur, le firewall `platform_admin` n'active de toute façon jamais ce
     * filtre.
     *
     * @param list<VatStatus>|null            $vatStatuses
     * @param list<CompanySizeCategory>|null  $companySizeCategories
     *
     * @return list<Uuid>
     */
    public function findCurrentOrganizationIdsMatching(?array $vatStatuses, ?array $companySizeCategories): array
    {
        $filters = $this->getEntityManager()->getFilters();
        $wasEnabled = $filters->isEnabled('tenant_filter');

        if ($wasEnabled) {
            $filters->suspend('tenant_filter');
        }

        try {
            $qb = $this->createQueryBuilder('fc')
                ->select('IDENTITY(fc.organization) AS organizationId')
                ->andWhere('fc.effectiveUntil IS NULL');

            if (null !== $vatStatuses && [] !== $vatStatuses) {
                $qb->andWhere('fc.vatStatus IN (:vatStatuses)')->setParameter('vatStatuses', $vatStatuses);
            }

            if (null !== $companySizeCategories && [] !== $companySizeCategories) {
                $qb->andWhere('fc.companySizeCategory IN (:companySizeCategories)')->setParameter('companySizeCategories', $companySizeCategories);
            }

            /** @var list<string> $rawIds */
            $rawIds = array_column($qb->getQuery()->getScalarResult(), 'organizationId');

            return array_map(static fn (string $id): Uuid => Uuid::fromString($id), $rawIds);
        } finally {
            if ($wasEnabled) {
                $filters->restore('tenant_filter');
            }
        }
    }
}
