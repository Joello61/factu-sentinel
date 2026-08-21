<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Identity\Entity\Membership;
use App\Identity\Entity\RefreshToken;
use App\Identity\Entity\User;
use App\Shared\Exception\AuthenticatedIdentityWithoutOrganizationException;
use App\Shared\Exception\OrganizationMembershipMismatchException;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Ajoute le claim `org` (UUID de l'Organization active) au JWT à sa création - jamais
 * `email_verified` : cette information doit rester lue depuis `GET /users/current`, pas
 * dupliquée dans un token qui n'est pas rafraîchi en temps réel (voir plan Phase 2).
 *
 * Depuis la Phase 14 (DEC-009), un User peut avoir plusieurs Membership. L'organisation
 * portée par le nouveau JWT est résolue dans cet ordre, jamais un autre :
 *
 * 1. **Choix explicite** - le payload transmis à `createFromPayload()` porte déjà `org`
 *    (App\Identity\Controller\SelectOrganizationController, qui a lui-même déjà validé
 *    l'appartenance réelle avant d'appeler le gestionnaire JWT) - jamais recalculé ni
 *    écrasé ici.
 * 2. **Rotation d'un refresh token** (POST /auth/refresh) - le token de sécurité courant
 *    est un `PostRefreshTokenAuthenticationToken` (gesdinet) porteur d'un
 *    App\Identity\Entity\RefreshToken dont `organizationId` est renseigné (voir
 *    App\Shared\Security\PropagateOrganizationToRefreshTokenListener, qui l'a renseigné à
 *    la précédente émission). Cette valeur n'est **jamais** une preuve d'appartenance en
 *    elle-même - revalidée ici contre les Membership réels de l'utilisateur courant
 *    (rechargé à chaque requête par le firewall JWT stateless, docs/12-roadmap.md bilan
 *    Phase 13) : si aucun Membership actif ne correspond (ex. l'utilisateur a été retiré de
 *    cette organisation entre-temps), le refresh est refusé
 *    (OrganizationMembershipMismatchException, 401) plutôt que de retomber silencieusement
 *    sur un autre choix.
 * 3. **Repli par défaut** (login initial, ou refresh token sans `organizationId` connu -
 *    ligne créée avant cette colonne) - le Membership le plus ancien (`createdAt` minimal),
 *    jamais présenté comme "l'organisation de l'utilisateur" mais comme un simple choix
 *    déterministe (docs/08-api-specification.md section 23, plan Phase 14).
 *
 * Un compte a toujours au moins un Membership dès la fin de son inscription
 * (RegisterController) ; zéro Membership reste une violation d'invariant interne (voir
 * AuthenticatedIdentityWithoutOrganizationException).
 *
 * L'organisation finalement résolue est toujours stockée sur l'attribut de requête
 * self::RESOLVED_ORGANIZATION_ATTRIBUTE, lu ensuite par
 * App\Shared\Security\PropagateOrganizationToRefreshTokenListener (sur
 * Lexik\Bundle\JWTAuthenticationBundle\Events::AUTHENTICATION_SUCCESS, dispatché après ce
 * listener dans le même cycle de requête) pour la reporter sur le refresh token émis à
 * cette occasion - jamais redécodée depuis le JWT déjà encodé.
 */
final class JwtOrganizationClaimListener
{
    public const string RESOLVED_ORGANIZATION_ATTRIBUTE = 'resolved_organization_id';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $data = $event->getData();

        if (isset($data['org']) && is_string($data['org']) && '' !== $data['org']) {
            // Déjà fixé explicitement par l'appelant (SelectOrganizationController) -
            // jamais recalculé/écrasé par la logique ci-dessous.
            $this->rememberResolvedOrganization($data['org']);

            return;
        }

        $inheritedMembership = $this->resolveFromRotatingRefreshToken($user);
        if (null !== $inheritedMembership) {
            $data['org'] = $inheritedMembership->getOrganization()->getId()->toRfc4122();
            $event->setData($data);
            $this->rememberResolvedOrganization($data['org']);

            return;
        }

        $memberships = $user->getMemberships();
        if (0 === count($memberships)) {
            throw new AuthenticatedIdentityWithoutOrganizationException(sprintf(
                'User "%s" has no Membership, expected at least 1.',
                $user->getUserIdentifier(),
            ));
        }

        $defaultMembership = null;
        foreach ($memberships as $candidate) {
            if (null === $defaultMembership || $candidate->getCreatedAt() < $defaultMembership->getCreatedAt()) {
                $defaultMembership = $candidate;
            }
        }

        $data['org'] = $defaultMembership->getOrganization()->getId()->toRfc4122();
        $event->setData($data);
        $this->rememberResolvedOrganization($data['org']);
    }

    /**
     * Retourne le Membership réel correspondant à l'organisation du refresh token en cours
     * de rotation, uniquement si ce Membership existe réellement pour l'utilisateur courant
     * - jamais la valeur brute du refresh token. Lève si un `organizationId` est présent
     * mais ne correspond à aucun Membership réel (refresh refusé, jamais un repli silencieux
     * vers une autre organisation).
     */
    private function resolveFromRotatingRefreshToken(User $user): ?Membership
    {
        $token = $this->tokenStorage->getToken();
        if (!$token instanceof PostRefreshTokenAuthenticationToken) {
            return null;
        }

        $refreshToken = $token->getRefreshToken();
        if (!$refreshToken instanceof RefreshToken) {
            return null;
        }

        $organizationId = $refreshToken->getOrganizationId();
        if (null === $organizationId) {
            // Ligne créée avant l'introduction de cette colonne, ou jamais encore
            // renseignée : aucune préférence connue, repli par défaut ci-dessus.
            return null;
        }

        foreach ($user->getMemberships() as $membership) {
            if ($membership->getOrganizationId()->equals($organizationId)) {
                return $membership;
            }
        }

        throw new OrganizationMembershipMismatchException(
            'Refresh token references an organization the user no longer belongs to.',
        );
    }

    private function rememberResolvedOrganization(string $organizationId): void
    {
        $this->requestStack->getCurrentRequest()?->attributes->set(self::RESOLVED_ORGANIZATION_ATTRIBUTE, $organizationId);
    }
}
