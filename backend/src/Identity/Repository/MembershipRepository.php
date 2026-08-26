<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\Membership;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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

    /**
     * Lecture cross-tenant explicite (plan Phase 15, App\PlatformAdmin\Controller\
     * GetOrganizationController) - `tenant_filter` n'est jamais actif sur le firewall
     * `platform_admin` (App\Shared\Security\TenantFilterActivationListener), donc jamais
     * besoin de le suspendre ici ; le filtrage par organisation est explicite par
     * construction de la requête, jamais délégué au filtre automatique.
     *
     * @return list<Membership>
     */
    public function findAllForOrganization(Uuid $organizationId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.organization = :organizationId')
            ->setParameter('organizationId', $organizationId->toRfc4122())
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * `target_type = ALL` (plan Phase 15, App\PlatformAdmin\Service\
     * PlatformNotificationRecipientResolver) - toutes les organisations, sans filtre. Jamais
     * appelée depuis un contexte tenant (`tenant_filter` n'est de toute façon jamais actif
     * sur le firewall `platform_admin`, donc rien à suspendre ici contrairement à
     * findAllForOrganization() qui filtre explicitement une seule organisation).
     *
     * @return list<Membership>
     */
    public function findAllAcrossOrganizations(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
