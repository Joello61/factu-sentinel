<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Support\ApiTestCase;
use Symfony\Component\BrowserKit\Cookie;

/**
 * single_use=true (config/packages/gesdinet_jwt_refresh_token.yaml) : chaque refresh
 * remplace le token consommé. Une vraie concurrence réseau (deux requêtes HTTP en
 * parallèle) n'est pas simulable avec le client de test en-process, synchrone par nature
 * - l'équivalent testable ici est séquentiel : consommer X une fois (succès), puis le
 * représenter (doit être rejeté), ce qui couvre exactement la garantie que single_use est
 * censé apporter. Une vérification réseau réelle relève du parcours manuel via Nginx
 * (voir plan Phase 2, étape 4).
 */
final class RefreshControllerTest extends ApiTestCase
{
    private const string ORIGIN = 'http://localhost:8080';

    public function testRefreshRotatesTheToken(): void
    {
        $client = $this->createAuthenticatedClient('refresh-rotation@example.test');
        $originalCookie = $this->firstCookie($client);

        $client->jsonRequest('POST', '/api/v1/auth/refresh', [], ['HTTP_ORIGIN' => self::ORIGIN]);

        self::assertResponseStatusCodeSame(200);
        $newCookie = $this->firstCookie($client);
        self::assertNotSame($originalCookie->getValue(), $newCookie->getValue(), 'Refresh token was not rotated.');
    }

    public function testReusingAnAlreadyConsumedRefreshTokenIsRejected(): void
    {
        $client = $this->createAuthenticatedClient('refresh-reuse@example.test');
        $spentCookie = $this->firstCookie($client);

        // Première utilisation : succès attendu, le token est consommé (single_use).
        $client->jsonRequest('POST', '/api/v1/auth/refresh', [], ['HTTP_ORIGIN' => self::ORIGIN]);
        self::assertResponseStatusCodeSame(200);

        // On force le cookie jar à représenter l'ancien token déjà consommé.
        $client->getCookieJar()->set($spentCookie);
        $client->jsonRequest('POST', '/api/v1/auth/refresh', [], ['HTTP_ORIGIN' => self::ORIGIN]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRefreshWithoutMatchingOriginIsRejected(): void
    {
        $client = $this->createAuthenticatedClient('refresh-origin@example.test');

        $client->jsonRequest('POST', '/api/v1/auth/refresh', [], ['HTTP_ORIGIN' => 'https://evil.example']);

        self::assertResponseStatusCodeSame(403);
    }

    private function firstCookie(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): Cookie
    {
        $cookies = $client->getCookieJar()->all();
        self::assertNotEmpty($cookies, 'Expected a refresh token cookie.');

        return array_values($cookies)[0];
    }
}
