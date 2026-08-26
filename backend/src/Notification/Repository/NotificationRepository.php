<?php

declare(strict_types=1);

namespace App\Notification\Repository;

use App\Identity\Entity\User;
use App\Notification\Entity\Notification;
use App\Shared\Security\CurrentOrganizationResolver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 *
 * Invariant non négociable (plan Phase 14, revue utilisateur du 21/08/2026) : toute méthode
 * de liste filtre systématiquement par `recipientUser`, jamais par la seule organisation
 * courante - `organization_id` seul laisserait un OWNER/ADMIN lire les notifications
 * adressées à un autre membre de sa propre organisation, ce qui n'est jamais l'invariant
 * voulu (docs/07-data-model.md, section 21).
 *
 * **Révision Phase 15** : Notification n'implémente plus TenantScopedInterface (voir cette
 * entité) - TenantFilter ne la filtre donc plus automatiquement. La restriction par
 * organisation est désormais explicite ici : `organization = organisation courante OU
 * organization IS NULL` (notification plateforme, portée cross-tenant, visible par son
 * destinataire quelle que soit son organisation active). C'est le seul repository/chemin de
 * lecture de cette entité dans tout le backend (audit explicite mené avant ce changement,
 * plan Phase 15) - aucun autre QueryBuilder, méthode find, ni accès direct n'existe.
 */
final class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CurrentOrganizationResolver $currentOrganizationResolver,
    ) {
        parent::__construct($registry, Notification::class);
    }

    /**
     * GET /notifications (docs/08-api-specification.md, section 34 : "paginé") - même patron
     * que App\Customer\Repository\CustomerRepository::paginate().
     *
     * @return array{items: list<Notification>, totalCount: int}
     */
    public function paginate(User $recipient, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.recipientUser = :recipient')
            ->andWhere('n.organization = :currentOrg OR n.organization IS NULL')
            ->setParameter('recipient', $recipient->getId())
            ->setParameter('currentOrg', $this->currentOrganizationResolver->getOrganizationId()->toRfc4122());

        // Le clone doit précéder tout ->orderBy() : PostgreSQL rejette un ORDER BY sur une
        // colonne absente du SELECT en présence d'un COUNT() (ni agrégée, ni groupée).
        $totalCount = (int) (clone $qb)
            ->select('COUNT(n.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('n.scheduledFor', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'totalCount' => $totalCount];
    }

    public function findOneForRecipient(string $id, User $recipient): ?Notification
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.id = :id')
            ->andWhere('n.recipientUser = :recipient')
            ->andWhere('n.organization = :currentOrg OR n.organization IS NULL')
            ->setParameter('id', $id)
            ->setParameter('recipient', $recipient->getId())
            ->setParameter('currentOrg', $this->currentOrganizationResolver->getOrganizationId()->toRfc4122())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
