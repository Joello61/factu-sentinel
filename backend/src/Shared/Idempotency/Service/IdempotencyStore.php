<?php

declare(strict_types=1);

namespace App\Shared\Idempotency\Service;

use App\Shared\Idempotency\Repository\IdempotencyKeyRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Point d'entrée unique pour honorer un en-tête Idempotency-Key (../../CLAUDE.md racine,
 * section 11 ; décision Phase 5 : store PostgreSQL, TTL applicatif 24h, jamais Redis avant
 * son besoin réel). $work est exécutée DANS la même transaction Doctrine que la
 * réservation (voir App\Shared\Idempotency\Repository\IdempotencyKeyRepository::reserve())
 * : si $work lève une exception, toute la transaction (y compris la réservation) est
 * annulée par l'appelant (wrapInTransaction), la clé redevient donc rejouable sans code de
 * nettoyage manuel ici.
 */
final class IdempotencyStore
{
    private const int TTL_HOURS = 24;

    public function __construct(
        private readonly IdempotencyKeyRepository $repository,
    ) {
    }

    /**
     * @param callable(): array{status: int, body: array<string, mixed>} $work
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function execute(Uuid $organizationId, string $key, callable $work): array
    {
        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d hours', self::TTL_HOURS));

        $reservedId = $this->repository->reserve($organizationId, $key, $expiresAt);

        if (null === $reservedId) {
            $existing = $this->repository->findValid($organizationId, $key);

            if (null === $existing) {
                // Ne devrait jamais arriver : reserve() n'échoue que si une ligne valide et
                // déjà committée existe (voir la garantie transactionnelle documentée dans
                // IdempotencyKeyRepository::reserve()). Défense en profondeur uniquement.
                throw new \RuntimeException('Idempotency-Key conflict without a readable stored response.');
            }

            return $existing;
        }

        $result = $work();

        $this->repository->complete($reservedId, $result['status'], $result['body']);

        return $result;
    }
}
