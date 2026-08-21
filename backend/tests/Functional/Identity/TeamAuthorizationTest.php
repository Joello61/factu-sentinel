<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Entity\Membership;
use App\Identity\Enum\Role;
use App\Tests\Support\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Matrice d'autorisation OWNER/ADMIN/COLLABORATOR (docs/04-product-requirements.md, section
 * 21.1 ; plan Phase 14, revue utilisateur du 21/08/2026) - un scénario positif et un négatif
 * par action, jamais un test générique (docs/09-test-strategy.md, section 23). Complète
 * explicitement la table combinée tenant x rôle demandée : ce fichier ne couvre que l'axe
 * rôle ; App\Tests\Integration\MultiTenant\TenantIsolationTest reste la source de vérité
 * pour l'axe tenant, jamais dupliqué ici.
 */
final class TeamAuthorizationTest extends ApiTestCase
{
    public function testOwnerCanInviteMember(): void
    {
        $client = $this->createAuthenticatedClient('owner-invite-pos@example.test');

        $client->jsonRequest('POST', '/api/v1/organizations/current/invitations', [
            'email' => 'invitee-owner@example.test',
            'role' => 'COLLABORATOR',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invite-'.bin2hex(random_bytes(8))]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('pending', $this->jsonBody($client)['data']['status']);
    }

    public function testCollaboratorCannotInviteMember(): void
    {
        $client = $this->createAuthenticatedClient('owner-invite-neg@example.test');
        $this->addMemberToOrganization('owner-invite-neg@example.test', 'collab-invite-neg@example.test', Role::COLLABORATOR);
        $collabToken = $this->loginExisting($client, 'collab-invite-neg@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$collabToken);
        $client->jsonRequest('POST', '/api/v1/organizations/current/invitations', [
            'email' => 'invitee-collab@example.test',
            'role' => 'COLLABORATOR',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invite-'.bin2hex(random_bytes(8))]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanInviteMember(): void
    {
        $client = $this->createAuthenticatedClient('owner-invite-admin@example.test');
        $this->addMemberToOrganization('owner-invite-admin@example.test', 'admin-invite-pos@example.test', Role::ADMIN);
        $adminToken = $this->loginExisting($client, 'admin-invite-pos@example.test');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);
        $client->jsonRequest('POST', '/api/v1/organizations/current/invitations', [
            'email' => 'invitee-admin@example.test',
            'role' => 'COLLABORATOR',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'invite-'.bin2hex(random_bytes(8))]);

        self::assertResponseStatusCodeSame(201);
    }

    /** team:read - les trois rôles peuvent consulter la liste des membres (plan Phase 14, décision #2). */
    public function testAllThreeRolesCanReadTeamMembers(): void
    {
        $client = $this->createAuthenticatedClient('owner-read@example.test');
        $this->addMemberToOrganization('owner-read@example.test', 'admin-read@example.test', Role::ADMIN);
        $this->addMemberToOrganization('owner-read@example.test', 'collab-read@example.test', Role::COLLABORATOR);

        foreach (['owner-read@example.test', 'admin-read@example.test', 'collab-read@example.test'] as $email) {
            $token = $this->loginExisting($client, $email);
            $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
            $client->jsonRequest('GET', '/api/v1/organizations/current/members');
            self::assertResponseStatusCodeSame(200, "team:read a échoué pour {$email}");
            self::assertCount(3, $this->jsonBody($client)['data']);
        }
    }

    public function testOnlyOwnerCanChangeAMemberRole(): void
    {
        $client = $this->createAuthenticatedClient('owner-role-neg@example.test');
        $admin = $this->addMemberToOrganization('owner-role-neg@example.test', 'admin-role-neg@example.test', Role::ADMIN);
        $target = $this->addMemberToOrganization('owner-role-neg@example.test', 'collab-role-target@example.test', Role::COLLABORATOR);
        $targetMembershipId = $this->findMembershipId($target->getId()->toRfc4122());

        $adminToken = $this->loginExisting($client, 'admin-role-neg@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current/members/'.$targetMembershipId, ['role' => 'ADMIN']);
        self::assertResponseStatusCodeSame(403, 'Un ADMIN ne doit jamais pouvoir changer un rôle.');

        $ownerToken = $this->loginExisting($client, 'owner-role-neg@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$ownerToken);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current/members/'.$targetMembershipId, ['role' => 'ADMIN']);
        self::assertResponseStatusCodeSame(200, 'Un OWNER doit pouvoir changer un rôle.');
        self::assertSame('ADMIN', $this->jsonBody($client)['data']['role']);
    }

    public function testOwnerRoleCanNeverBeChanged(): void
    {
        $client = $this->createAuthenticatedClient('owner-immutable@example.test');
        $ownerMembershipId = $this->findMembershipId($this->currentUserId($client));

        $client->jsonRequest('PATCH', '/api/v1/organizations/current/members/'.$ownerMembershipId, ['role' => 'ADMIN']);

        self::assertResponseStatusCodeSame(409);
    }

    public function testAdminCanRemoveACollaboratorButNeverTheOwner(): void
    {
        $client = $this->createAuthenticatedClient('owner-remove@example.test');
        $this->addMemberToOrganization('owner-remove@example.test', 'admin-remove@example.test', Role::ADMIN);
        $collaborator = $this->addMemberToOrganization('owner-remove@example.test', 'collab-remove@example.test', Role::COLLABORATOR);
        $collaboratorMembershipId = $this->findMembershipId($collaborator->getId()->toRfc4122());
        $ownerMembershipId = $this->findMembershipId($this->currentUserId($client));

        $adminToken = $this->loginExisting($client, 'admin-remove@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$adminToken);

        $client->jsonRequest('DELETE', '/api/v1/organizations/current/members/'.$ownerMembershipId);
        self::assertResponseStatusCodeSame(403, 'Un ADMIN ne doit jamais pouvoir retirer un OWNER.');

        $client->jsonRequest('DELETE', '/api/v1/organizations/current/members/'.$collaboratorMembershipId);
        self::assertResponseStatusCodeSame(204, 'Un ADMIN doit pouvoir retirer un COLLABORATOR.');
    }

    public function testCollaboratorCannotRemoveAnyMember(): void
    {
        $client = $this->createAuthenticatedClient('owner-remove-neg@example.test');
        $collaborator = $this->addMemberToOrganization('owner-remove-neg@example.test', 'collab-remove-neg@example.test', Role::COLLABORATOR);
        $target = $this->addMemberToOrganization('owner-remove-neg@example.test', 'target-remove-neg@example.test', Role::COLLABORATOR);
        $targetMembershipId = $this->findMembershipId($target->getId()->toRfc4122());

        $collabToken = $this->loginExisting($client, 'collab-remove-neg@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$collabToken);
        $client->jsonRequest('DELETE', '/api/v1/organizations/current/members/'.$targetMembershipId);

        self::assertResponseStatusCodeSame(403);
    }

    public function testOnlyOwnerAndAdminCanSendTeamNotification(): void
    {
        $client = $this->createAuthenticatedClient('owner-notify@example.test');
        $recipient = $this->addMemberToOrganization('owner-notify@example.test', 'collab-notify@example.test', Role::COLLABORATOR);
        $recipientId = $recipient->getId()->toRfc4122();

        $client->jsonRequest('POST', '/api/v1/organizations/current/notifications', [
            'recipient_ids' => [$recipientId],
            'message' => 'Bienvenue dans l\'équipe.',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'notify-'.bin2hex(random_bytes(8))]);
        self::assertResponseStatusCodeSame(201, 'Un OWNER doit pouvoir envoyer une notification.');

        $collabToken = $this->loginExisting($client, 'collab-notify@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$collabToken);
        $client->jsonRequest('POST', '/api/v1/organizations/current/notifications', [
            'recipient_ids' => [$recipientId],
            'message' => 'Ne devrait jamais partir.',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'notify-'.bin2hex(random_bytes(8))]);
        self::assertResponseStatusCodeSame(403, 'Un COLLABORATOR ne doit jamais pouvoir envoyer une notification d\'équipe.');
    }

    public function testOnlyOwnerAndAdminCanUpdateOrganization(): void
    {
        $client = $this->createAuthenticatedClient('owner-org-update@example.test');
        $this->addMemberToOrganization('owner-org-update@example.test', 'collab-org-update@example.test', Role::COLLABORATOR);

        $collabToken = $this->loginExisting($client, 'collab-org-update@example.test');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$collabToken);
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', ['legal_name' => 'Ne devrait jamais passer']);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Cœur de la revue de sécurité (plan Phase 14) : un access token valide et correctement
     * signé, mais dont le claim `org` ne correspond plus à aucun Membership réel (retrait
     * survenu après émission), doit être rejeté - jamais accepté sur la seule foi du claim.
     */
    public function testRemovedMembershipInvalidatesAlreadyIssuedToken(): void
    {
        $client = $this->createAuthenticatedClient('owner-revoke-mid@example.test');
        $collaborator = $this->addMemberToOrganization('owner-revoke-mid@example.test', 'collab-revoke-mid@example.test', Role::COLLABORATOR);
        $collabToken = $this->loginExisting($client, 'collab-revoke-mid@example.test');

        $collaboratorMembershipId = $this->findMembershipId($collaborator->getId()->toRfc4122());

        // Retrait direct en base - contourne DELETE .../members/{id} pour isoler l'objet du
        // test (la revalidation JWT, pas le endpoint de retrait lui-même, déjà couvert
        // ci-dessus).
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $membership = $em->find(Membership::class, $collaboratorMembershipId);
        self::assertNotNull($membership);
        $em->remove($membership);
        $em->flush();

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$collabToken);
        $client->jsonRequest('GET', '/api/v1/organizations/current/members');

        self::assertResponseStatusCodeSame(401);
    }

    public function testSelectOrganizationRejectsAnOrganizationTheCallerDoesNotBelongTo(): void
    {
        $client = $this->createAuthenticatedClient('owner-select-neg@example.test');

        $client->jsonRequest('POST', '/api/v1/auth/select-organization', [
            // UUID syntaxiquement valide (variant RFC4122) mais n'existant nulle part -
            // Symfony\Component\Validator\Constraints\Uuid rejette un format invalide
            // (comme un nibble de variant incorrect) avant même d'atteindre le contrôleur,
            // ce qui n'est pas ce que ce test veut exercer.
            'organization_id' => \Symfony\Component\Uid\Uuid::v4()->toRfc4122(),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    private function currentUserId(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): string
    {
        $client->jsonRequest('GET', '/api/v1/users/current');

        return $this->jsonBody($client)['data']['id'];
    }

    private function findMembershipId(string $userId): string
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $membership = $em->getRepository(Membership::class)->createQueryBuilder('m')
            ->andWhere('m.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertNotNull($membership, "Aucun Membership trouvé pour l'utilisateur {$userId}.");

        return $membership->getId()->toRfc4122();
    }
}
