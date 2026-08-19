<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use App\Shared\Idempotency\Service\IdempotencyStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Algorithme d'idempotence sûr sous concurrence réelle (plan Phase 5, section
 * "Idempotency-Key" ; docs/09-test-strategy.md, revue utilisateur du plan : le test de
 * concurrence réelle est prioritaire sur un test séquentiel).
 *
 * testConcurrentReservationBlocksUntilFirstTransactionCommits reproduit une vraie
 * concurrence via un second processus PHP (proc_open, pcntl indisponible dans cet
 * environnement) ouvrant sa propre connexion PostgreSQL : le process concurrent doit
 * bloquer sur son propre INSERT ... ON CONFLICT tant que la transaction du process
 * principal n'a pas committé, exactement comme documenté dans
 * App\Shared\Idempotency\Repository\IdempotencyKeyRepository::reserve().
 */
final class IdempotencyStoreTest extends KernelTestCase
{
    public function testReplayReturnsStoredResponseWithoutReRunningWork(): void
    {
        self::bootKernel();
        $store = self::getContainer()->get(IdempotencyStore::class);

        $organizationId = Uuid::v7();
        $callCount = 0;
        $work = function () use (&$callCount): array {
            ++$callCount;

            return ['status' => 200, 'body' => ['data' => ['call' => $callCount]]];
        };

        $first = $store->execute($organizationId, 'replay-key', $work);
        $second = $store->execute($organizationId, 'replay-key', $work);

        self::assertSame($first, $second);
        self::assertSame(1, $callCount, 'Le travail ne doit jamais être rejoué pour une clé déjà honorée.');
    }

    public function testDifferentKeysAreIndependent(): void
    {
        self::bootKernel();
        $store = self::getContainer()->get(IdempotencyStore::class);

        $organizationId = Uuid::v7();
        $callCount = 0;
        $work = function () use (&$callCount): array {
            ++$callCount;

            return ['status' => 200, 'body' => ['data' => ['call' => $callCount]]];
        };

        $store->execute($organizationId, 'key-a', $work);
        $store->execute($organizationId, 'key-b', $work);

        self::assertSame(2, $callCount);
    }

    public function testConcurrentReservationBlocksUntilFirstTransactionCommits(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();

        $organizationId = Uuid::v7()->toRfc4122();
        $key = 'concurrent-key';

        $childScript = <<<'PHP'
            <?php
            [$host, $port, $dbname, $user, $password, $organizationId, $key] = array_slice($argv, 1);
            $pdo = new PDO(sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbname), $user, $password);
            $started = microtime(true);
            $stmt = $pdo->prepare(<<<'SQL'
                INSERT INTO idempotency_keys (id, organization_id, idempotency_key, response_status, response_body, expires_at, created_at)
                VALUES (gen_random_uuid(), :org, :key, NULL, NULL, NOW() + INTERVAL '24 hours', NOW())
                ON CONFLICT (organization_id, idempotency_key)
                DO UPDATE SET id = EXCLUDED.id, response_status = NULL, response_body = NULL, expires_at = EXCLUDED.expires_at, created_at = NOW()
                WHERE idempotency_keys.expires_at < NOW()
                RETURNING id
                SQL);
            $stmt->execute(['org' => $organizationId, 'key' => $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $elapsedMs = (int) ((microtime(true) - $started) * 1000);
            fwrite(STDOUT, json_encode(['reserved' => false !== $row, 'elapsed_ms' => $elapsedMs]));
            PHP;

        $childScriptPath = tempnam(sys_get_temp_dir(), 'idempotency_child_');
        self::assertIsString($childScriptPath);
        file_put_contents($childScriptPath, $childScript);

        $connection = $em->getConnection();
        $connection->beginTransaction();
        $connection->executeStatement(
            "INSERT INTO idempotency_keys (id, organization_id, idempotency_key, response_status, response_body, expires_at, created_at) VALUES (gen_random_uuid(), :org, :key, NULL, NULL, NOW() + INTERVAL '24 hours', NOW())",
            ['org' => $organizationId, 'key' => $key],
        );

        $process = proc_open(
            ['php', $childScriptPath, (string) $params['host'], (string) $params['port'], (string) $params['dbname'], (string) $params['user'], (string) $params['password'], $organizationId, $key],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);

        // Laisse le temps au process concurrent d'atteindre son propre INSERT et de s'y
        // bloquer réellement (attend la transaction principale, toujours ouverte ici).
        usleep(300_000);
        $status = proc_get_status($process);
        self::assertTrue($status['running'], 'Le process concurrent doit être bloqué sur son INSERT tant que la transaction principale ne committe pas.');

        $connection->commit();

        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        unlink($childScriptPath);

        self::assertIsString($output, $errorOutput);
        $result = json_decode($output, true, flags: \JSON_THROW_ON_ERROR);

        self::assertFalse($result['reserved'], "Après déblocage, la clé est déjà occupée par la transaction principale : aucune seconde réservation ne doit être créée.");
        self::assertGreaterThanOrEqual(250, $result['elapsed_ms'], 'Le process concurrent doit avoir réellement attendu le commit, pas juste réussi immédiatement.');

        $rowCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM idempotency_keys WHERE organization_id = ? AND idempotency_key = ?', [$organizationId, $key]);
        self::assertSame(1, $rowCount, 'Une seule ligne pour cette clé, jamais deux réservations concurrentes.');
    }
}
