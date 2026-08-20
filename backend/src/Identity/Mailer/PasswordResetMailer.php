<?php

declare(strict_types=1);

namespace App\Identity\Mailer;

use App\Identity\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

/**
 * Envoie le lien de récupération de compte (US-AUTH-003). Transport dev/test :
 * MAILER_DSN "null://null" (voir .env).
 */
final readonly class PasswordResetMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $frontendUrl,
        private string $mailerFrom,
    ) {
    }

    public function send(User $user, ResetPasswordToken $resetToken): void
    {
        $resetUrl = sprintf('%s/reset-password?token=%s', rtrim($this->frontendUrl, '/'), $resetToken->getToken());

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe - FactuSentinel')
            ->text(sprintf("Réinitialisez votre mot de passe en suivant ce lien :\n%s\n\nCe lien expire dans un délai limité.", $resetUrl));

        $this->mailer->send($email);
    }
}
