<?php

declare(strict_types=1);

namespace App\AI\Repository;

use App\AI\Entity\AiCallLogEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiCallLogEntry>
 */
final class AiCallLogEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiCallLogEntry::class);
    }

    /**
     * Agrégat cross-tenant explicite (plan Phase 15, App\AI\Service\AiUsageReaderInterface) -
     * AiCallLogEntry est tenant-scoped (TenantScopedInterface) : cette méthode suspend
     * temporairement `tenant_filter` (même patron que
     * App\Identity\Repository\InvitationRepository::findOneBySelector()) le temps de cette
     * unique requête, plutôt que de compter sur le fait que le firewall `platform_admin`
     * n'active jamais ce filtre - défense en profondeur si cette méthode était un jour
     * appelée depuis un contexte tenant.
     *
     * @return array{volume: int, estimatedCost: string}
     */
    public function aggregateUsageSince(\DateTimeImmutable $since): array
    {
        $filters = $this->getEntityManager()->getFilters();
        $wasEnabled = $filters->isEnabled('tenant_filter');

        if ($wasEnabled) {
            $filters->suspend('tenant_filter');
        }

        try {
            $result = $this->createQueryBuilder('a')
                ->select('COUNT(a.id) AS volume', 'COALESCE(SUM(a.estimatedCost), 0) AS estimatedCost')
                ->andWhere('a.createdAt >= :since')
                ->setParameter('since', $since)
                ->getQuery()
                ->getSingleResult();

            return [
                'volume' => (int) $result['volume'],
                'estimatedCost' => (string) $result['estimatedCost'],
            ];
        } finally {
            if ($wasEnabled) {
                $filters->restore('tenant_filter');
            }
        }
    }
}
