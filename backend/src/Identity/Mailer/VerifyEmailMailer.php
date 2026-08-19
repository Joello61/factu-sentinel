<?php

declare(strict_types=1);

namespace App\Identity\Mailer;

use App\Identity\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Envoie le lien de vérification d'email (US-AUTH-001, non bloquant pour l'usage de base
 * du compte — docs/08-api-specification.md, section 7). Transport dev/test : MAILER_DSN
 * "null://null" (aucun envoi réel, voir .env).
 */
final readonly class VerifyEmailMailer
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
        private string $frontendUrl,
        private string $mailerFrom,
    ) {
    }

    public function send(User $user): void
    {
        // La signature est générée pour la route API elle-même : sa query string
        // (expires, signature, ...) est ensuite recopiée telle quelle dans le lien
        // frontend envoyé par email. Le frontend affiche une page, puis rappelle
        // GET /api/v1/auth/verify-email/{id} avec cette même query string — chemin et
        // paramètres identiques à ce qui a été signé, condition nécessaire à la validation
        // (VerifyEmailHelper::validateEmailConfirmationFromRequest).
        $signature = $this->verifyEmailHelper->generateSignature(
            'auth_verify_email',
            $user->getId()->toRfc4122(),
            $user->getEmail(),
            ['id' => $user->getId()->toRfc4122()],
        );

        $queryString = (string) parse_url($signature->getSignedUrl(), PHP_URL_QUERY);

        $verifyUrl = sprintf(
            '%s/verify-email/%s?%s',
            rtrim($this->frontendUrl, '/'),
            $user->getId()->toRfc4122(),
            $queryString,
        );

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject('Vérifiez votre adresse email — FactuSentinel')
            ->text(sprintf("Confirmez votre adresse email en suivant ce lien :\n%s", $verifyUrl));

        $this->mailer->send($email);
    }
}
