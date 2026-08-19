<?php

declare(strict_types=1);

namespace App\Tests\Functional\Customer;

use App\Tests\Support\ApiTestCase;

final class GetCustomerControllerTest extends ApiTestCase
{
    public function testGetOwnCustomerSucceeds(): void
    {
        $client = $this->createAuthenticatedClient('customer-get-001@example.test');

        $client->jsonRequest('POST', '/api/v1/customers', ['customer_type' => 'PARTICULIER', 'name' => 'Jean Dupont', 'country' => 'FR']);
        $id = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('GET', '/api/v1/customers/'.$id);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('Jean Dupont', $this->jsonBody($client)['data']['name']);
    }

    public function testGetUnknownIdReturns404(): void
    {
        $client = $this->createAuthenticatedClient('customer-get-002@example.test');

        $client->jsonRequest('GET', '/api/v1/customers/00000000-0000-7000-8000-000000000000');
        self::assertResponseStatusCodeSame(404);
    }

    public function testGetMalformedIdReturns404NotBadRequest(): void
    {
        $client = $this->createAuthenticatedClient('customer-get-003@example.test');

        $client->jsonRequest('GET', '/api/v1/customers/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * docs/09-test-strategy.md, section 22 : jamais 403, jamais confirmer l'existence
     * d'une ressource d'un autre tenant (backend/CLAUDE.md, section 6).
     */
    public function testGetOtherTenantCustomerReturns404(): void
    {
        $client = static::createClient();
        $tokenA = $this->loginAs($client, 'customer-get-tenant-a@example.test');
        $tokenB = $this->loginAs($client, 'customer-get-tenant-b@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('POST', '/api/v1/customers', ['customer_type' => 'PARTICULIER', 'name' => 'Client A', 'country' => 'FR']);
        $customerAId = $this->jsonBody($client)['data']['id'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('GET', '/api/v1/customers/'.$customerAId);

        self::assertResponseStatusCodeSame(404);
    }
}
