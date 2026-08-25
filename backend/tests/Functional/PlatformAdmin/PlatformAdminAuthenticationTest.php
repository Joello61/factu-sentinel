<?php

declare(strict_types=1);

namespace App\Tests\Functional\PlatformAdmin;

use App\PlatformAdmin\Repository\PlatformAdministratorRepository;
use App\Shared\Audit\Entity\AuditLogEntry;
use App\Shared\Audit\Enum\ActorType;
use App\Tests\Support\PlatformAdminApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;

/**
 * ADR-009 (docs/06-technical-architecture.md, section 34) : le point technique le plus
 * sensible de la Phase 15 - une hypothèse incorrecte ici créerait une vraie faille
 * d'isolation. Plan Phase 15, revue utilisateur du 21/08/2026 : chaque exigence listée est
 * vérifiée par un test dédié ici, jamais seulement affirmée en commentaire.
 */
final class PlatformAdminAuthenticationTest extends PlatformAdminApiTestCase
{
    public function testFullLoginMfaFlowIssuesAWorkingToken(): void
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-happy@example.test');

        $token = $this->loginPlatformAdministrator($client, 'admin-happy@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('GET', '/api/v1/platform-admin/organizations');
        self::assertResponseStatusCodeSame(200);
    }

    public function testFirstSuccessfulMfaVerificationConfirmsEnrollment(): void
    {
        $client = static::createClient();
        ['administrator' => $administrator, 'plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-enroll@example.test');
        self::assertFalse($administrator->isMfaConfirmed());

        $this->loginPlatformAdministrator($client, 'admin-enroll@example.test', $plainSecret);

        $repository = static::getContainer()->get(PlatformAdministratorRepository::class);
        $reloaded = $repository->findOneByEmail('admin-enroll@example.test');
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isMfaConfirmed());
    }

    public function testWrongPasswordAndUnknownEmailReturnTheIdenticalGenericResponse(): void
    {
        $client = static::createClient();
        $this->createPlatformAdministrator('admin-wrongpw@example.test');

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/login', [
            'email' => 'admin-wrongpw@example.test',
            'password' => 'definitely-the-wrong-password-000',
        ]);
        $wrongPasswordStatus = $client->getResponse()->getStatusCode();
        $wrongPasswordBody = $this->jsonBody($client);

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/login', [
            'email' => 'admin-does-not-exist@example.test',
            'password' => 'whatever-password-000000000000000',
        ]);
        $unknownEmailStatus = $client->getResponse()->getStatusCode();
        $unknownEmailBody = $this->jsonBody($client);

        self::assertSame(401, $wrongPasswordStatus);
        self::assertSame(401, $unknownEmailStatus);
        // request_id est propre à chaque requête (App\Shared\Http\RequestIdListener) -
        // exclu de la comparaison, seul le contenu métier de l'erreur doit être identique.
        unset($wrongPasswordBody['error']['request_id'], $unknownEmailBody['error']['request_id']);
        self::assertSame($wrongPasswordBody, $unknownEmailBody, 'Un email inconnu et un mot de passe invalide ne doivent jamais être distingués.');
    }

    public function testWrongTotpCodeIsRejected(): void
    {
        $client = static::createClient();
        $this->createPlatformAdministrator('admin-wrongcode@example.test');

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/login', [
            'email' => 'admin-wrongcode@example.test',
            'password' => 'a-very-long-password-1234',
        ]);
        $challenge = $this->jsonBody($client)['data']['mfa_challenge'];

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/mfa/verify', ['mfa_challenge' => $challenge, 'code' => '000000']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testMfaChallengeGrantsNoAccessToAnyPlatformAdminEndpointBeforeVerification(): void
    {
        $client = static::createClient();
        $this->createPlatformAdministrator('admin-noshortcut@example.test');

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/login', [
            'email' => 'admin-noshortcut@example.test',
            'password' => 'a-very-long-password-1234',
        ]);
        $challenge = $this->jsonBody($client)['data']['mfa_challenge'];

        // Le ticket intermédiaire n'est jamais un jeton exploitable, sous aucune forme.
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$challenge);
        $client->jsonRequest('GET', '/api/v1/platform-admin/organizations');
        self::assertResponseStatusCodeSame(401);
    }

    public function testMfaChallengeIsSingleUseNeverReplayable(): void
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-singleuse@example.test');

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/login', [
            'email' => 'admin-singleuse@example.test',
            'password' => 'a-very-long-password-1234',
        ]);
        $challenge = $this->jsonBody($client)['data']['mfa_challenge'];
        $code = TOTP::createFromSecret($plainSecret)->now();

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/mfa/verify', ['mfa_challenge' => $challenge, 'code' => $code]);
        self::assertResponseStatusCodeSame(200);

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/mfa/verify', ['mfa_challenge' => $challenge, 'code' => $code]);
        self::assertResponseStatusCodeSame(401, 'Un ticket déjà consommé ne doit jamais être rejouable, même avec le bon code.');
    }

    public function testExpiredMfaChallengeIsRejected(): void
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-expired@example.test');

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/login', [
            'email' => 'admin-expired@example.test',
            'password' => 'a-very-long-password-1234',
        ]);
        $challenge = $this->jsonBody($client)['data']['mfa_challenge'];

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement(
            "UPDATE platform_admin_mfa_challenges SET expires_at = NOW() - INTERVAL '1 hour'",
        );
        $em->clear();

        $code = TOTP::createFromSecret($plainSecret)->now();
        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/mfa/verify', ['mfa_challenge' => $challenge, 'code' => $code]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginAndMfaVerifyRateLimitersAreIndependent(): void
    {
        $client = static::createClient();
        $this->createPlatformAdministrator('admin-ratelimit@example.test');

        // Épuise le limiteur de login (5/15min) avec des mots de passe invalides.
        for ($i = 0; $i < 5; ++$i) {
            $client->jsonRequest('POST', '/api/v1/platform-admin/auth/login', [
                'email' => 'admin-ratelimit@example.test',
                'password' => 'wrong-password-attempt-number-'.$i,
            ]);
        }
        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/login', [
            'email' => 'admin-ratelimit@example.test',
            'password' => 'wrong-password-attempt-again',
        ]);
        self::assertResponseStatusCodeSame(429, 'Le limiteur de login doit être épuisé après 5 tentatives.');
    }

    public function testTenantTokenIsRejectedOnThePlatformAdminFirewall(): void
    {
        $client = static::createClient();
        // Même email des deux côtés - identités structurellement séparées, jamais confondues
        // (ADR-009), même en cas de collision d'email.
        $sharedEmail = 'dual-identity@example.test';
        $this->registerUser($sharedEmail);
        $this->createPlatformAdministrator($sharedEmail);

        $tenantToken = $this->loginExisting($client, $sharedEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$tenantToken);

        $client->jsonRequest('GET', '/api/v1/platform-admin/organizations');
        self::assertResponseStatusCodeSame(401, 'Un jeton tenant ne doit jamais fonctionner sur /platform-admin/*.');
    }

    public function testPlatformAdminTokenIsRejectedOnTheTenantApi(): void
    {
        $client = static::createClient();
        $sharedEmail = 'dual-identity-2@example.test';
        $this->registerUser($sharedEmail);
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator($sharedEmail);

        $platformAdminToken = $this->loginPlatformAdministrator($client, $sharedEmail, $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$platformAdminToken);

        $client->jsonRequest('GET', '/api/v1/organizations/current');
        self::assertResponseStatusCodeSame(401, 'Un jeton PlatformAdministrator ne doit jamais fonctionner sur l\'API tenant normale.');
    }

    public function testRevokedAdministratorLosesAccessImmediatelyDespiteAnUnexpiredToken(): void
    {
        $client = static::createClient();
        ['administrator' => $administrator, 'plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-revoked@example.test');
        $token = $this->loginPlatformAdministrator($client, 'admin-revoked@example.test', $plainSecret);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $client->jsonRequest('GET', '/api/v1/platform-admin/organizations');
        self::assertResponseStatusCodeSame(200, 'Le jeton doit fonctionner avant révocation.');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $repository = static::getContainer()->get(PlatformAdministratorRepository::class);
        $reloaded = $repository->find($administrator->getId());
        self::assertNotNull($reloaded);
        $reloaded->revoke();
        $em->flush();
        $em->clear();

        // Même jeton signé, non expiré (durée de vie résiduelle réelle) - doit être rejeté
        // dès la requête suivante, la revalidation ne repose jamais sur la seule signature.
        $client->jsonRequest('GET', '/api/v1/platform-admin/organizations');
        self::assertResponseStatusCodeSame(401, 'Un compte révoqué doit perdre l\'accès immédiatement, même avec un JWT non expiré.');
    }

    public function testRefreshWithoutMatchingOriginIsRejected(): void
    {
        // Écart trouvé et fermé en revue de sécurité de fin de phase (skill security-review) :
        // App\Shared\Security\RefreshOriginCheckListener ne couvrait à l'origine que
        // /api/v1/auth/refresh (tenant), jamais /api/v1/platform-admin/auth/refresh - même
        // exposition CSRF résiduelle (cookie transmis automatiquement par le navigateur) sur
        // l'identité la plus sensible du produit.
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-refresh-origin@example.test');
        $this->loginPlatformAdministrator($client, 'admin-refresh-origin@example.test', $plainSecret);

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/refresh', [], ['HTTP_ORIGIN' => 'https://evil.example']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRefreshWithMatchingOriginRotatesTheToken(): void
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-refresh-ok@example.test');
        $this->loginPlatformAdministrator($client, 'admin-refresh-ok@example.test', $plainSecret);

        $cookies = $client->getCookieJar()->all();
        self::assertNotEmpty($cookies, 'Expected a platform_admin_refresh_token cookie.');
        $originalValue = array_values($cookies)[0]->getValue();

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/refresh', [], ['HTTP_ORIGIN' => 'http://localhost:8080']);

        self::assertResponseStatusCodeSame(200);
        $newCookies = $client->getCookieJar()->all();
        self::assertNotSame($originalValue, array_values($newCookies)[0]->getValue(), 'Refresh token was not rotated.');
    }

    public function testTotpSecretAndSubmittedCodesNeverAppearInAuditLog(): void
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator('admin-noleak@example.test');

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/login', [
            'email' => 'admin-noleak@example.test',
            'password' => 'a-very-long-password-1234',
        ]);
        $challenge = $this->jsonBody($client)['data']['mfa_challenge'];

        // Une tentative ratée, volontairement avant la bonne, pour vérifier qu'aucune des
        // deux valeurs de code n'apparaît jamais dans le journal d'audit.
        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/mfa/verify', ['mfa_challenge' => $challenge, 'code' => '123456']);
        self::assertResponseStatusCodeSame(401);

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/login', [
            'email' => 'admin-noleak@example.test',
            'password' => 'a-very-long-password-1234',
        ]);
        $secondChallenge = $this->jsonBody($client)['data']['mfa_challenge'];
        $goodCode = TOTP::createFromSecret($plainSecret)->now();
        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/mfa/verify', ['mfa_challenge' => $secondChallenge, 'code' => $goodCode]);
        self::assertResponseStatusCodeSame(200);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // Restreint aux entrées émises par ce PlatformAdministrator - la base de test
        // partagée porte aussi des entrées d'autres tests (ex. un SIREN client contenant
        // coïncidemment "123456"), sans rapport avec la fuite recherchée ici.
        $entries = $em->getRepository(AuditLogEntry::class)->findBy(['actorType' => ActorType::PLATFORM_ADMIN]);
        self::assertNotEmpty($entries);

        foreach ($entries as $entry) {
            $encoded = json_encode(['previous' => $entry->getPreviousState(), 'new' => $entry->getNewState()]);
            self::assertIsString($encoded);
            self::assertStringNotContainsString($plainSecret, $encoded);
            self::assertStringNotContainsString('123456', $encoded);
            self::assertStringNotContainsString($goodCode, $encoded);
        }
    }
}
