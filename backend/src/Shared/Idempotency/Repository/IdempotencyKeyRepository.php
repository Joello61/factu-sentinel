<?php

declare(strict_types=1);

namespace App\Shared\Idempotency\Repository;

use App\Shared\Idempotency\Entity\IdempotencyKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types as DbalTypes;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * Accès en SQL natif via la connexion DBAL de l'EntityManager courant, jamais via
 * persist()/find() de l'ORM : la garantie de sécurité sous concurrence de cette classe
 * repose sur un UPSERT PostgreSQL atomique (voir reserve()) exécuté DANS la transaction
 * Doctrine déjà ouverte par l'appelant (App\Shared\Idempotency\Service\IdempotencyStore,
 * lui-même appelé depuis wrapInTransaction()) -- le connection->executeQuery() participe à
 * cette même transaction, contrairement à un persist()/flush() ORM qui introduirait un
 * cycle de vie d'entité inutile ici. App\Shared\Idempotency\Entity\IdempotencyKey ne sert
 * donc que de source de mapping pour le schéma/les migrations, jamais hydratée en usage
 * normal (voir plan Phase 5, section "Idempotency-Key").
 *
 * @extends ServiceEntityRepository<IdempotencyKey>
 */
final class IdempotencyKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdempotencyKey::class);
    }

    /**
     * UPSERT atomique (algorithme détaillé dans le plan Phase 5, section "Idempotency-Key") :
     * réserve la clé pour cette transaction si elle est libre ou expirée. Sous isolation
     * READ COMMITTED de PostgreSQL, un INSERT en conflit avec une ligne non committée d'une
     * transaction concurrente bloque jusqu'à résolution de cette transaction -- il n'existe
     * donc jamais d'état "en cours de traitement" observable côté application : soit cette
     * méthode retourne l'id réservé (à cette transaction d'exécuter le vrai travail), soit
     * elle retourne null et findValid() renvoie alors une réponse déjà committée.
     */
    public function reserve(Uuid $organizationId, string $key, \DateTimeImmutable $expiresAt): ?Uuid
    {
        $connection = $this->getEntityManager()->getConnection();

        $id = Uuid::v7();

        $result = $connection->executeQuery(
            <<<'SQL'
                INSERT INTO idempotency_keys (id, organization_id, idempotency_key, response_status, response_body, expires_at, created_at)
                VALUES (:id, :organizationId, :key, NULL, NULL, :expiresAt, NOW())
                ON CONFLICT (organization_id, idempotency_key)
                DO UPDATE SET id = EXCLUDED.id, response_status = NULL, response_body = NULL, expires_at = EXCLUDED.expires_at, created_at = NOW()
                WHERE idempotency_keys.expires_at < NOW()
                RETURNING id
                SQL,
            [
                'id' => $id,
                'organizationId' => $organizationId,
                'key' => $key,
                'expiresAt' => $expiresAt,
            ],
            [
                'id' => DbalTypes::GUID,
                'organizationId' => DbalTypes::GUID,
                'key' => DbalTypes::STRING,
                'expiresAt' => DbalTypes::DATETIME_IMMUTABLE,
            ],
        );

        $row = $result->fetchAssociative();

        return false !== $row ? Uuid::fromString((string) $row['id']) : null;
    }

    /** @return array{status: int, body: array<string, mixed>}|null */
    public function findValid(Uuid $organizationId, string $key): ?array
    {
        $connection = $this->getEntityManager()->getConnection();

        $row = $connection->executeQuery(
            'SELECT response_status, response_body FROM idempotency_keys WHERE organization_id = :organizationId AND idempotency_key = :key AND expires_at >= NOW()',
            ['organizationId' => $organizationId, 'key' => $key],
            ['organizationId' => DbalTypes::GUID, 'key' => DbalTypes::STRING],
        )->fetchAssociative();

        if (false === $row || null === $row['response_status']) {
            return null;
        }

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $row['response_body'], true, flags: \JSON_THROW_ON_ERROR);

        return ['status' => (int) $row['response_status'], 'body' => $body];
    }

    /** @param array<string, mixed> $responseBody */
    public function complete(Uuid $id, int $responseStatus, array $responseBody): void
    {
        $connection = $this->getEntityManager()->getConnection();

        $connection->executeStatement(
            'UPDATE idempotency_keys SET response_status = :status, response_body = :body WHERE id = :id',
            [
                'id' => $id,
                'status' => $responseStatus,
                'body' => $responseBody,
            ],
            [
                'id' => DbalTypes::GUID,
                'status' => DbalTypes::INTEGER,
                'body' => DbalTypes::JSON,
            ],
        );
    }
}
