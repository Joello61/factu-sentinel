<?php

declare(strict_types=1);

namespace App\Document\Repository;

use App\Document\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Document>
 */
final class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Un document supprimé (logiquement) n'est jamais résolvable via cette méthode - même
     * convention que App\Customer\Repository\CustomerRepository::findActive (soft delete,
     * docs/07-data-model.md, section 30). TenantFilter s'applique déjà automatiquement
     * (organization_id), inutile de le répéter ici.
     */
    public function findActive(Uuid $id): ?Document
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.id = :id')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('id', $id, UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Document> */
    public function findActiveForInvoice(Uuid $invoiceId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.invoice = :invoiceId')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('invoiceId', $invoiceId, UuidType::NAME)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
