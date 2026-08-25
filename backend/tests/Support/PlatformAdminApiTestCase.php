<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\PlatformAdmin\Entity\PlatformAdministrator;
use App\PlatformAdmin\Service\PlatformAdminMfaService;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Étend App\Tests\Support\ApiTestCase (jamais une classe séparée sans lien) : les tests
 * Platform Admin ont systématiquement besoin de comptes tenant (App\Identity\Entity\User) en
 * plus des comptes App\PlatformAdmin\Entity\PlatformAdministrator, pour prouver l'isolation
 * dans les deux sens (ADR-009, plan Phase 15).
 */
abstract class PlatformAdminApiTestCase extends ApiTestCase
{
    /**
     * Persiste un PlatformAdministrator directement en base (contourne
     * app:platform-admin:create, même raisonnement que ApiTestCase::registerUser()) - MFA
     * volontairement non confirmé par défaut (totpConfirmedAt = null), comme un compte
     * fraîchement provisionné.
     *
     * @return array{administrator: PlatformAdministrator, plainSecret: string}
     */
    protected function createPlatformAdministrator(string $email, string $password = 'a-very-long-password-1234'): array
    {
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);
        /** @var PlatformAdminMfaService $mfaService */
        $mfaService = $container->get(PlatformAdminMfaService::class);

        $plainSecret = $mfaService->generatePlainSecret();

        $administrator = new PlatformAdministrator($email, 'temporary', $mfaService->encrypt($plainSecret));
        $administrator->setPassword($hasher->hashPassword($administrator, $password));

        $em->persist($administrator);
        $em->flush();

        return ['administrator' => $administrator, 'plainSecret' => $plainSecret];
    }

    /**
     * Flux complet login + MFA (deux appels HTTP réels, jamais un raccourci direct vers
     * l'émission du JWT) - même exigence que le plan Phase 15 : l'état intermédiaire
     * mfa_required doit être traversé, pas contourné, même dans les tests.
     *
     * Efface l'en-tête Authorization avant les deux appels (même patron que
     * App\Tests\Support\ApiTestCase::loginExisting()) : un jeton tenant laissé sur le
     * client par un appel précédent serait présenté au firewall platform_admin, dont
     * l'authenticator tenterait de le décoder avec la mauvaise clé et rejetterait la
     * requête en 401 avant même d'atteindre le contrôleur - PUBLIC_ACCESS sur /auth/login
     * ne dispense jamais Symfony Security de tenter l'authentification d'un jeton présent.
     */
    protected function loginPlatformAdministrator(KernelBrowser $client, string $email, string $plainSecret, string $password = 'a-very-long-password-1234'): string
    {
        $previousAuthorization = $client->getServerParameter('HTTP_AUTHORIZATION');
        $client->setServerParameter('HTTP_AUTHORIZATION', '');

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/login', ['email' => $email, 'password' => $password]);
        $loginBody = $this->jsonBody($client);
        $challenge = $loginBody['data']['mfa_challenge'] ?? null;
        \assert(is_string($challenge), 'Login did not return an mfa_challenge: '.json_encode($loginBody));

        $code = TOTP::createFromSecret($plainSecret)->now();

        $client->jsonRequest('POST', '/api/v1/platform-admin/auth/mfa/verify', ['mfa_challenge' => $challenge, 'code' => $code]);
        $verifyBody = $this->jsonBody($client);
        $accessToken = $verifyBody['data']['token'] ?? null;
        \assert(is_string($accessToken), 'MFA verification did not return an access token: '.json_encode($verifyBody));

        if (is_string($previousAuthorization)) {
            $client->setServerParameter('HTTP_AUTHORIZATION', $previousAuthorization);
        }

        return $accessToken;
    }

    /** Crée un PlatformAdministrator, se connecte (login + MFA), positionne l'en-tête Authorization sur un client déjà existant (createClient() ne peut être appelé qu'une fois par test, même contrainte que ApiTestCase). */
    protected function createAuthenticatedPlatformAdminClient(string $email, string $password = 'a-very-long-password-1234'): KernelBrowser
    {
        $client = static::createClient();
        ['plainSecret' => $plainSecret] = $this->createPlatformAdministrator($email, $password);
        $token = $this->loginPlatformAdministrator($client, $email, $plainSecret, $password);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        return $client;
    }
}
