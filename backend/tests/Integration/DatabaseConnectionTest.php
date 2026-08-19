<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test d'intégration requis par la Phase 1 (docs/12-roadmap.md) : vérifie que
 * l'application peut effectivement se connecter à PostgreSQL, pas seulement que le
 * conteneur de services le déclare configuré.
 */
final class DatabaseConnectionTest extends KernelTestCase
{
    public function testItConnectsToPostgreSql(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);

        self::assertSame(1, (int) $connection->executeQuery('SELECT 1')->fetchOne());
        self::assertInstanceOf(PostgreSQLPlatform::class, $connection->getDatabasePlatform());
    }
}
