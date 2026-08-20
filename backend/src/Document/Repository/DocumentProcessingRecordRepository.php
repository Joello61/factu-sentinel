<?php

declare(strict_types=1);

namespace App\Document\Repository;

use App\Document\Entity\DocumentProcessingRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<DocumentProcessingRecord>
 */
final class DocumentProcessingRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentProcessingRecord::class);
    }

    /**
     * Verrou pessimiste transactionnel (plan Phase 7, invariant d'idempotence) : nécessaire
     * pour qu'une redélivrance Messenger et une vraie course entre deux workers consommant
     * le même message ne puissent jamais toutes les deux réclamer (claim()) la même
     * tentative - doit être appelée à l'intérieur d'une transaction déjà ouverte
     * (App\Document\MessageHandler\ExtractDocumentContentHandler), jamais en dehors.
     */
    public function findForUpdate(Uuid $id): ?DocumentProcessingRecord
    {
        return $this->find($id, LockMode::PESSIMISTIC_WRITE);
    }
}
