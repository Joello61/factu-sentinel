<?php

declare(strict_types=1);

namespace App\Tests\Functional\Customer;

use App\Tests\Support\ApiTestCase;

final class CreateCustomerControllerTest extends ApiTestCase
{
    public function testProfessionnelFrancaisWithSirenIsCreated(): void
    {
        $client = $this->createAuthenticatedClient('customer-create-001@example.test');

        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PROFESSIONNEL_FRANCAIS',
            'name' => 'Client SARL',
            'siren' => '123456789',
            'country' => 'FR',
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = $this->jsonBody($client)['data'];
        self::assertSame('PROFESSIONNEL_FRANCAIS', $data['customer_type']);
        self::assertSame('123456789', $data['siren']);
    }

    /**
     * Plan Phase 4, décision D1 : l'absence de SIREN pour un PROFESSIONNEL_FRANCAIS n'est
     * jamais une erreur de validation (docs/05-user-stories.md, US-CUSTOMER-002 ; CLAUDE.md
     * racine section 9, BR-COMPLIANCE-003/ADR-002). Corrige la contradiction avec l'ancienne
     * formulation de docs/08-api-specification.md, section 26 ("422 si siren manquant").
     */
    public function testProfessionnelFrancaisWithoutSirenIsCreatedNotRejected(): void
    {
        $client = $this->createAuthenticatedClient('customer-create-002@example.test');

        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PROFESSIONNEL_FRANCAIS',
            'name' => 'Client Sans Siren SARL',
            'country' => 'FR',
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = $this->jsonBody($client)['data'];
        self::assertNull($data['siren']);

        // Phase 5 (Compliance Engine) n'existe pas encore : aucune AuditLogEntry ne doit
        // référencer un finding/A_VERIFIER, seule la création du client elle-même est auditée.
        $container = static::getContainer();
        $em = $container->get(\Doctrine\ORM\EntityManagerInterface::class);
        $events = $em->getConnection()->fetchFirstColumn(
            'SELECT event_type FROM audit_log_entries WHERE entity_type = ? AND entity_id = ?',
            ['Customer', $data['id']],
        );
        self::assertSame(['CUSTOMER_CREATED'], $events);
    }

    public function testParticulierIsCreated(): void
    {
        $client = $this->createAuthenticatedClient('customer-create-003@example.test');

        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PARTICULIER',
            'name' => 'Jean Dupont',
            'country' => 'FR',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('PARTICULIER', $this->jsonBody($client)['data']['customer_type']);
    }

    public function testProfessionnelEtrangerIsCreated(): void
    {
        $client = $this->createAuthenticatedClient('customer-create-004@example.test');

        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PROFESSIONNEL_ETRANGER',
            'name' => 'Foreign Corp Ltd',
            'vat_number' => 'DE123456789',
            'country' => 'DE',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('DE123456789', $this->jsonBody($client)['data']['vat_number']);
    }

    public function testMissingCountryFailsValidation(): void
    {
        $client = $this->createAuthenticatedClient('customer-create-005@example.test');

        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PARTICULIER',
            'name' => 'Jean Dupont',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION_ERROR', $this->jsonBody($client)['error']['code']);
    }

    public function testInvalidSirenFormatFailsValidation(): void
    {
        $client = $this->createAuthenticatedClient('customer-create-006@example.test');

        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PROFESSIONNEL_FRANCAIS',
            'name' => 'Client SARL',
            'siren' => 'not-a-siren',
            'country' => 'FR',
        ]);

        self::assertResponseStatusCodeSame(422);
    }
}
