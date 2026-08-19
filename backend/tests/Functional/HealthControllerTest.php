<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Couvre l'Exit Criteria de la Phase 1 (docs/12-roadmap.md) : un appel API de bout en
 * bout (ici, à travers le kernel de test plutôt qu'à travers Nginx) atteint bien
 * Symfony et confirme la connexion à PostgreSQL.
 */
final class HealthControllerTest extends WebTestCase
{
    public function testHealthEndpointReportsDatabaseOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('ok', $payload['data']['status']);
        self::assertSame('ok', $payload['data']['database']);
    }
}
