<?php

declare(strict_types=1);

namespace App\Shared\Audit\Repository;

use App\Shared\Audit\Entity\AuditLogEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLogEntry>
 *
 * Volontairement sans méthode de recherche pour cette phase (aucune page "Historique
 * d'audit" au périmètre de la Phase 3) : append-only via AuditLogger, rien de plus.
 */
final class AuditLogEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLogEntry::class);
    }
}
