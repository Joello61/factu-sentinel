<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Service;

use App\PlatformAdmin\Entity\PlatformAdminMfaChallenge;
use App\PlatformAdmin\Entity\PlatformAdministrator;
use App\PlatformAdmin\Repository\PlatformAdminMfaChallengeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Émission/résolution du ticket MFA intermédiaire (App\PlatformAdmin\Entity\
 * PlatformAdminMfaChallenge) - même principe que App\Identity\Service\InvitationTokenResolver
 * (sélecteur 32 caractères hexadécimaux + vérificateur, jamais le jeton en clair persisté,
 * comparaison en temps constant).
 */
final readonly class PlatformAdminMfaChallengeResolver
{
    private const int SELECTOR_LENGTH = 32;
    private const int TTL_SECONDS = 300;

    public function __construct(
        private PlatformAdminMfaChallengeRepository $challengeRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function issue(PlatformAdministrator $administrator): string
    {
        $selector = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $verifier);

        $challenge = new PlatformAdminMfaChallenge(
            $administrator,
            $selector,
            $tokenHash,
            new \DateTimeImmutable(sprintf('+%d seconds', self::TTL_SECONDS)),
        );

        $this->entityManager->persist($challenge);

        return $selector.$verifier;
    }

    /** Ne retourne jamais d'information permettant de distinguer un jeton mal formé d'un jeton inconnu, expiré ou déjà consommé - toujours null, à l'appelant de décider de la réponse HTTP. */
    public function resolve(string $plainToken): ?PlatformAdminMfaChallenge
    {
        if (\strlen($plainToken) <= self::SELECTOR_LENGTH) {
            return null;
        }

        $selector = substr($plainToken, 0, self::SELECTOR_LENGTH);
        $verifier = substr($plainToken, self::SELECTOR_LENGTH);

        $challenge = $this->challengeRepository->findOneBySelector($selector);
        if (null === $challenge) {
            return null;
        }

        if (!hash_equals($challenge->getTokenHash(), hash('sha256', $verifier))) {
            return null;
        }

        if (!$challenge->isUsable()) {
            return null;
        }

        return $challenge;
    }
}
