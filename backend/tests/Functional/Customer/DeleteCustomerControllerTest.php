<?php

declare(strict_types=1);

namespace App\Tests\Functional\Customer;

use App\Tests\Support\ApiTestCase;

final class DeleteCustomerControllerTest extends ApiTestCase
{
    public function testSoftDeletedCustomerDisappearsFromGetAndList(): void
    {
        $client = $this->createAuthenticatedClient('customer-delete-001@example.test');

        $client->jsonRequest('POST', '/api/v1/customers', ['customer_type' => 'PARTICULIER', 'name' => 'À supprimer', 'country' => 'FR']);
        $id = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('DELETE', '/api/v1/customers/'.$id);
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('GET', '/api/v1/customers/'.$id);
        self::assertResponseStatusCodeSame(404);

        $client->jsonRequest('GET', '/api/v1/customers');
        $names = array_column($this->jsonBody($client)['data'], 'name');
        self::assertNotContains('À supprimer', $names);
    }

    /**
     * docs/07-data-model.md, section 30 : suppression logique uniquement, jamais physique.
     */
    public function testDeleteIsLogicalNotPhysical(): void
    {
        $client = $this->createAuthenticatedClient('customer-delete-002@example.test');

        $client->jsonRequest('POST', '/api/v1/customers', ['customer_type' => 'PARTICULIER', 'name' => 'À supprimer', 'country' => 'FR']);
        $id = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('DELETE', '/api/v1/customers/'.$id);
        self::assertResponseStatusCodeSame(204);

        $container = static::getContainer();
        $em = $container->get(\Doctrine\ORM\EntityManagerInterface::class);
        $row = $em->getConnection()->fetchAssociative('SELECT deleted_at FROM customers WHERE id = ?', [$id]);

        self::assertNotFalse($row);
        self::assertNotNull($row['deleted_at']);
    }
}
