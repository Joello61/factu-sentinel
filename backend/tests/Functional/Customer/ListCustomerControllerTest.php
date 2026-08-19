<?php

declare(strict_types=1);

namespace App\Tests\Functional\Customer;

use App\Tests\Support\ApiTestCase;

final class ListCustomerControllerTest extends ApiTestCase
{
    public function testListReturnsOnlyOwnOrganizationCustomers(): void
    {
        $client = static::createClient();
        $tokenA = $this->loginAs($client, 'customer-list-tenant-a@example.test');
        $tokenB = $this->loginAs($client, 'customer-list-tenant-b@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PARTICULIER',
            'name' => 'Client A',
            'country' => 'FR',
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PARTICULIER',
            'name' => 'Client B',
            'country' => 'FR',
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('GET', '/api/v1/customers');
        self::assertResponseStatusCodeSame(200);
        $body = $this->jsonBody($client);

        $names = array_column($body['data'], 'name');
        self::assertContains('Client B', $names);
        self::assertNotContains('Client A', $names, "Tenant B ne doit jamais voir les clients du Tenant A.");
    }

    public function testFilterByCustomerType(): void
    {
        $client = $this->createAuthenticatedClient('customer-list-filter@example.test');

        $client->jsonRequest('POST', '/api/v1/customers', ['customer_type' => 'PARTICULIER', 'name' => 'Particulier 1', 'country' => 'FR']);
        $client->jsonRequest('POST', '/api/v1/customers', ['customer_type' => 'PROFESSIONNEL_FRANCAIS', 'name' => 'Pro 1', 'country' => 'FR']);

        $client->jsonRequest('GET', '/api/v1/customers?customer_type=PROFESSIONNEL_FRANCAIS');
        self::assertResponseStatusCodeSame(200);
        $body = $this->jsonBody($client);

        self::assertCount(1, $body['data']);
        self::assertSame('Pro 1', $body['data'][0]['name']);
    }

    public function testPaginationMeta(): void
    {
        $client = $this->createAuthenticatedClient('customer-list-pagination@example.test');

        for ($i = 1; $i <= 3; ++$i) {
            $client->jsonRequest('POST', '/api/v1/customers', ['customer_type' => 'PARTICULIER', 'name' => 'Client '.$i, 'country' => 'FR']);
        }

        $client->jsonRequest('GET', '/api/v1/customers?page=1&per_page=2');
        self::assertResponseStatusCodeSame(200);
        $body = $this->jsonBody($client);

        self::assertCount(2, $body['data']);
        self::assertSame(3, $body['meta']['pagination']['total_count']);
        self::assertSame(2, $body['meta']['pagination']['total_pages']);
    }
}
