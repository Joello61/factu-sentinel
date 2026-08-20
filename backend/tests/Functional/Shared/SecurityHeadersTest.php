<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * docs/10-security-privacy.md, section 47 - Phase 10. App\Shared\Http\SecurityHeadersListener
 * s'applique à toute réponse "main request" indépendamment de l'authentification (vérifié
 * ici sur /api/health, seule route PUBLIC_ACCESS sans dépendance à un compte de test).
 */
final class SecurityHeadersTest extends WebTestCase
{
    public function testSecurityHeadersArePresentOnEveryResponse(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        $headers = $client->getResponse()->headers;

        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('strict-origin-when-cross-origin', $headers->get('Referrer-Policy'));
        self::assertSame('DENY', $headers->get('X-Frame-Options'));
        self::assertSame("default-src 'none'; frame-ancestors 'none'", $headers->get('Content-Security-Policy'));
        self::assertNotNull($headers->get('Permissions-Policy'));
    }

    /**
     * HSTS_ENABLED=false par défaut (backend/.env, App\Shared\Http\HstsHeaderListener) :
     * environnement de test en HTTP, aucun domaine de production maîtrisé - le header ne
     * doit jamais être envoyé tant que ce drapeau n'est pas explicitement activé (voir le
     * docblock de HstsHeaderListener pour la justification complète).
     */
    public function testHstsHeaderAbsentByDefault(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        self::assertNull($client->getResponse()->headers->get('Strict-Transport-Security'));
    }
}
