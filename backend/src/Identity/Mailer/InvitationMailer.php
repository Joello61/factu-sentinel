<?php

declare(strict_types=1);

namespace App\Identity\Mailer;

use App\Identity\Entity\Invitation;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Envoie le lien d'invitation (US-TEAM-001, plan Phase 14) - même patron que
 * App\Identity\Mailer\VerifyEmailMailer (transport dev/test : MAILER_DSN "null://null").
 *
 * Le jeton en clair (sélecteur + vérificateur) n'est reçu qu'ici, jamais persisté ailleurs
 * que sous sa forme hachée sur Invitation - voir App\Identity\Service\InviteMemberService.
 */
final readonly class InvitationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $frontendUrl,
        private string $mailerFrom,
    ) {
    }

    public function send(Invitation $invitation, string $plainToken): void
    {
        $acceptUrl = sprintf('%s/invitations/%s', rtrim($this->frontendUrl, '/'), $plainToken);

        $organizationName = $invitation->getOrganization()->getLegalName() ?? 'une organisation FactuSentinel';

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($invitation->getEmail())
            ->subject('Invitation à rejoindre ' . $organizationName . ' sur FactuSentinel')
            ->text(sprintf(
                "Vous avez été invité à rejoindre %s sur FactuSentinel avec le rôle %s.\n\nAcceptez l'invitation en suivant ce lien :\n%s\n\nCe lien expire le %s.",
                $organizationName,
                $invitation->getRole()->value,
                $acceptUrl,
                $invitation->getExpiresAt()->format('d/m/Y'),
            ));

        $this->mailer->send($email);
    }
}
