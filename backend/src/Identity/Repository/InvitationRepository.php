<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\Invitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
final class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    /**
     * Recherche par sélecteur uniquement (docs/backend/CLAUDE.md section 9 : jamais le
     * vérificateur en clair dans une requête) - la comparaison du hash complet se fait en
     * temps constant côté appelant (hash_equals), pas ici.
     *
     * TenantFilter reste actif pour cette entité (Invitation implémente
     * TenantScopedInterface) : ce lookup, appelé depuis GET /invitations/{token} (public,
     * filtre jamais activé faute de JWT) et POST /invitations/{token}/accept (authentifié,
     * filtre actif sur l'organisation *courante* de l'appelant - jamais celle de
     * l'invitation, qu'il ne rejoint pas encore), doit donc suspendre temporairement le
     * filtre le temps de cette unique requête. `suspend()`/`restore()` (Doctrine
     * FilterCollection) conservent la même instance de filtre entre les deux appels -
     * contrairement à `disable()`/`enable()`, qui réinstancie un filtre vierge et perdrait
     * le paramètre `organization_id` déjà positionné pour le reste de la requête -, donc
     * aucune valeur à re-capturer ni à re-quoter manuellement.
     */
    public function findOneBySelector(string $selector): ?Invitation
    {
        $filters = $this->getEntityManager()->getFilters();
        $wasEnabled = $filters->isEnabled('tenant_filter');

        if ($wasEnabled) {
            $filters->suspend('tenant_filter');
        }

        try {
            return $this->createQueryBuilder('i')
                ->andWhere('i.tokenSelector = :selector')
                ->setParameter('selector', $selector)
                ->getQuery()
                ->getOneOrNullResult();
        } finally {
            if ($wasEnabled) {
                $filters->restore('tenant_filter');
            }
        }
    }

    /**
     * Filtré par TenantFilter (organisation courante) - utilisé pour empêcher une double
     * invitation active sur le même email au sein d'une même organisation (contrainte
     * unique partielle en base, voir migration - ce contrôle applicatif produit un message
     * d'erreur explicite avant d'atteindre la contrainte SQL).
     */
    public function findActivePendingByEmail(string $email): ?Invitation
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.email = :email')
            ->andWhere('i.status = :status')
            ->setParameter('email', $email)
            ->setParameter('status', \App\Identity\Enum\InvitationStatus::PENDING)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
