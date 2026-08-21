<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\Invitation;
use App\Identity\Repository\InvitationRepository;

/**
 * Résolution d'un jeton d'invitation en clair (sélecteur 32 caractères hexadécimaux +
 * vérificateur) vers l'Invitation correspondante - centralisée pour que
 * App\Identity\Controller\GetInvitationPreviewController (public) et
 * App\Identity\Controller\AcceptInvitationController (authentifié) appliquent exactement la
 * même vérification, jamais une variante divergente d'un controller à l'autre.
 *
 * Ne retourne jamais d'information permettant de distinguer un jeton syntaxiquement invalide
 * d'un jeton bien formé mais inconnu ou dont le hash ne correspond pas - toujours null dans
 * les trois cas, à l'appelant de décider de la réponse HTTP.
 */
final readonly class InvitationTokenResolver
{
    private const int SELECTOR_LENGTH = 32;

    public function __construct(
        private InvitationRepository $invitationRepository,
    ) {
    }

    public function resolve(string $plainToken): ?Invitation
    {
        if (\strlen($plainToken) <= self::SELECTOR_LENGTH) {
            return null;
        }

        $selector = substr($plainToken, 0, self::SELECTOR_LENGTH);
        $verifier = substr($plainToken, self::SELECTOR_LENGTH);

        $invitation = $this->invitationRepository->findOneBySelector($selector);
        if (null === $invitation) {
            return null;
        }

        if (!hash_equals($invitation->getTokenHash(), hash('sha256', $verifier))) {
            return null;
        }

        return $invitation;
    }
}
