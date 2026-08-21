<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Identity\Entity\Membership;
use App\Identity\Enum\Role;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Organization\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Le rate limiter (config/packages/rate_limiter.yaml) persiste sur le disque
        // (FilesystemAdapter) indépendamment de la base de données : sans ce reset, tous
        // les tests d'un même processus partagent le même compteur pour l'IP 127.0.0.1 du
        // client de test, et finissent par se bloquer entre eux en 429.
        static::getContainer()->get('cache.rate_limiter')->clear();
        static::ensureKernelShutdown();
    }

    protected function jsonRequest(KernelBrowser $client, string $method, string $uri, array $payload = []): void
    {
        $client->jsonRequest($method, $uri, $payload);
    }

    /** @return array<string, mixed> */
    protected function jsonBody(KernelBrowser $client): array
    {
        $content = $client->getResponse()->getContent();
        \assert(is_string($content));

        return json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Crée un compte directement en base (contourne l'API pour ne pas dépendre du
     * contrôleur d'inscription dans les tests qui ne portent pas sur lui), authentifie le
     * client et positionne l'en-tête Authorization. static::createClient() ne pouvant être
     * appelé qu'une seule fois par test (WebTestCase), les scénarios multi-comptes doivent
     * appeler celle-ci une seule fois puis utiliser loginAs() sur le même client pour
     * obtenir un second jeton (voir TenantIsolationTest).
     */
    protected function createAuthenticatedClient(string $email, string $password = 'a-very-long-password-1234'): KernelBrowser
    {
        $client = static::createClient();
        $token = $this->loginAs($client, $email, $password);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        return $client;
    }

    /**
     * Crée un compte et se connecte avec, sur un client déjà existant, sans en créer un
     * nouveau (createClient() ne peut être appelé qu'une fois par test). Ne modifie pas
     * l'en-tête Authorization du client : à l'appelant de basculer explicitement entre les
     * jetons de deux comptes distincts sur le même client.
     */
    protected function loginAs(KernelBrowser $client, string $email, string $password = 'a-very-long-password-1234'): string
    {
        $this->registerUser($email, $password);

        return $this->loginExisting($client, $email, $password);
    }

    /**
     * Se connecte avec un compte déjà créé (par loginAs(), registerUser() ou
     * addMemberToOrganization() - plan Phase 14) sans tenter de le recréer - jamais
     * loginAs() pour un compte déjà existant, qui échouerait sur la contrainte unique
     * d'email (voir la découverte documentée dans le plan Phase 14).
     */
    protected function loginExisting(KernelBrowser $client, string $email, string $password = 'a-very-long-password-1234'): string
    {
        $previousAuthorization = $client->getServerParameter('HTTP_AUTHORIZATION');
        $client->setServerParameter('HTTP_AUTHORIZATION', '');

        $client->jsonRequest('POST', '/api/v1/auth/login', ['email' => $email, 'password' => $password]);
        $body = $this->jsonBody($client);
        $accessToken = $body['data']['token'] ?? null;
        \assert(is_string($accessToken), 'Login did not return an access token: '.json_encode($body));

        if (is_string($previousAuthorization)) {
            $client->setServerParameter('HTTP_AUTHORIZATION', $previousAuthorization);
        }

        return $accessToken;
    }

    /**
     * Persiste un compte (+ Organization vide + Membership OWNER) directement en base,
     * sans se connecter. Utile pour les tests qui n'ont besoin que du compte existant
     * (ex. mot de passe oublié) et n'ont donc pas besoin d'un jeton.
     */
    protected function registerUser(string $email, string $password = 'a-very-long-password-1234'): User
    {
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User($email, 'temporary');
        $user->setPassword($hasher->hashPassword($user, $password));
        $organization = new Organization();
        $membership = new Membership($user, $organization, Role::OWNER);

        $em->persist($organization);
        $em->persist($user);
        $em->persist($membership);
        $em->flush();

        return $user;
    }

    /**
     * Marque l'email d'un compte déjà créé (registerUser()/createAuthenticatedClient()) comme
     * vérifié - ces deux méthodes créent volontairement un compte non vérifié par défaut
     * (User::$emailVerifiedAt reste null), donc tout test touchant à une fonctionnalité gardée
     * par la vérification email (Phase 8 : App\AI\Controller\*) doit appeler explicitement
     * cette méthode après authentification.
     */
    protected function markEmailVerified(string $email): void
    {
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $user = $container->get(UserRepository::class)->findOneByEmail($email);
        \assert($user instanceof User, "Aucun compte trouvé pour {$email}.");

        $user->markEmailAsVerified();
        $em->flush();
    }

    /**
     * Remet un compte à l'état non vérifié après un markEmailVerified() précédent - utile
     * pour un scénario qui a besoin de franchir une étape gardée par la vérification email
     * (ex. déclencher une analyse de conformité, Phase 10) puis de tester ensuite un appel
     * qui doit être rejeté précisément parce que le compte n'est pas vérifié. App\Identity\
     * Entity\User n'expose volontairement aucun setter symétrique à markEmailAsVerified()
     * (aucun besoin métier réel de "dé-vérifier" un email) - même raisonnement que
     * App\Tests\Functional\Compliance\RuleVersionNonRetroactivityTest pour RuleVersion :
     * une mutation SQL directe pour un état que l'entité n'a jamais besoin d'exposer en
     * production, jamais un contournement via réflexion.
     */
    protected function markEmailUnverified(string $email): void
    {
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement(
            'UPDATE users SET email_verified_at = NULL WHERE email = ?',
            [$email],
        );
        $em->clear();
    }

    /**
     * Ajoute directement en base un second (ou N-ième) Membership sur l'organisation d'un
     * compte déjà créé (plan Phase 14) - jamais via registerUser(), qui crée systématiquement
     * une nouvelle Organization vide. Contourne l'API d'invitation (POST .../invitations puis
     * accept) pour les tests qui ne portent pas sur ce parcours lui-même, même raisonnement
     * que registerUser() contournant POST /auth/register.
     */
    protected function addMemberToOrganization(string $ownerEmail, string $memberEmail, Role $role, string $password = 'a-very-long-password-1234'): User
    {
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $userRepository = $container->get(UserRepository::class);

        $owner = $userRepository->findOneByEmail($ownerEmail);
        \assert($owner instanceof User, "Aucun compte trouvé pour {$ownerEmail}.");
        $organization = $owner->getMemberships()->first()->getOrganization();

        $member = new User($memberEmail, 'temporary');
        $member->setPassword($hasher->hashPassword($member, $password));
        $membership = new Membership($member, $organization, $role);

        $em->persist($member);
        $em->persist($membership);
        $em->flush();

        return $member;
    }
}
