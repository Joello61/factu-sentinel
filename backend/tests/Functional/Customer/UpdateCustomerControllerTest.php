<?php

declare(strict_types=1);

namespace App\Tests\Functional\Customer;

use App\Tests\Support\ApiTestCase;

final class UpdateCustomerControllerTest extends ApiTestCase
{
    public function testPartialUpdateChangesOnlyProvidedFields(): void
    {
        $client = $this->createAuthenticatedClient('customer-update-001@example.test');

        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PROFESSIONNEL_FRANCAIS',
            'name' => 'Client SARL',
            'siren' => '123456789',
            'country' => 'FR',
        ]);
        $id = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('PATCH', '/api/v1/customers/'.$id, ['name' => 'Client SARL Renommé']);

        self::assertResponseStatusCodeSame(200);
        $data = $this->jsonBody($client)['data'];
        self::assertSame('Client SARL Renommé', $data['name']);
        self::assertSame('123456789', $data['siren'], 'siren non fourni dans le PATCH doit être conservé.');
    }

    public function testUpdateOtherTenantCustomerReturns404(): void
    {
        $client = static::createClient();
        $tokenA = $this->loginAs($client, 'customer-update-tenant-a@example.test');
        $tokenB = $this->loginAs($client, 'customer-update-tenant-b@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenA);
        $client->jsonRequest('POST', '/api/v1/customers', ['customer_type' => 'PARTICULIER', 'name' => 'Client A', 'country' => 'FR']);
        $customerAId = $this->jsonBody($client)['data']['id'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tokenB);
        $client->jsonRequest('PATCH', '/api/v1/customers/'.$customerAId, ['name' => 'Renamed by B']);

        self::assertResponseStatusCodeSame(404);
    }
}
