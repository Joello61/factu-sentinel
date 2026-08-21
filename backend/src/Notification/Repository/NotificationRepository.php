<?php

declare(strict_types=1);

namespace App\Notification\Repository;

use App\Identity\Entity\User;
use App\Notification\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 *
 * Invariant non négociable (plan Phase 14, revue utilisateur du 21/08/2026) : toute méthode
 * de liste filtre systématiquement par `recipientUser`, jamais par la seule organisation
 * courante (déjà appliquée par TenantFilter) - `organization_id` seul laisserait un
 * OWNER/ADMIN lire les notifications adressées à un autre membre de sa propre organisation,
 * ce qui n'est jamais l'invariant voulu (docs/07-data-model.md, section 21).
 */
final class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
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
            ->setParameter('recipient', $recipient->getId());

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
            ->setParameter('id', $id)
            ->setParameter('recipient', $recipient->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
