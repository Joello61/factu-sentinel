<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Service;

use OTPHP\TOTP;
use Psr\Clock\ClockInterface;

/**
 * Encapsule la génération/vérification TOTP (`spomky-labs/otphp` ^11.5, vérifié actif/maintenu
 * et compatible PHP 8.4 - `../CLAUDE.md` section 3, plan Phase 15) et le chiffrement au repos
 * du secret (`sodium_crypto_secretbox`, libsodium natif PHP 8.4, aucune dépendance
 * supplémentaire) - jamais dispersé ailleurs dans le module (`App\PlatformAdmin\Entity\
 * PlatformAdministrator::$totpSecret` ne contient jamais le secret en clair).
 *
 * MFA obligatoire sans exception pour `PlatformAdministrator` (docs/10-security-privacy.md,
 * section 17 bis) - ce service est le seul point du backend qui manipule un secret TOTP en
 * clair, et seulement de façon transitoire (jamais journalisé, jamais dans un AuditLogEntry).
 */
final readonly class PlatformAdminMfaService
{
    private const int SECRET_BYTES = 32;

    public function __construct(
        private ClockInterface $clock,
        private string $platformAdminTotpEncryptionKeyHex,
    ) {
    }

    /** Secret TOTP en clair (base32) - jamais persisté tel quel, voir encrypt(). */
    public function generatePlainSecret(): string
    {
        return TOTP::generate($this->clock, self::SECRET_BYTES)->getSecret();
    }

    public function getProvisioningUri(string $plainSecret, string $administratorEmail): string
    {
        $totp = TOTP::createFromSecret($plainSecret, $this->clock)
            ->withLabel($administratorEmail)
            ->withIssuer('FactuSentinel Platform Admin');

        return $totp->getProvisioningUri();
    }

    public function verifyCode(string $plainSecret, string $code): bool
    {
        return TOTP::createFromSecret($plainSecret, $this->clock)->verify($code);
    }

    /** sodium_crypto_secretbox(nonce aléatoire par appel, jamais réutilisé) - stocké nonce||ciphertext, encodé base64. */
    public function encrypt(string $plainSecret): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plainSecret, $nonce, $this->rawKey());

        return base64_encode($nonce.$ciphertext);
    }

    public function decrypt(string $encryptedSecret): string
    {
        $raw = base64_decode($encryptedSecret, true);
        if (false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Malformed encrypted TOTP secret.');
        }

        $nonce = substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plainSecret = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->rawKey());
        if (false === $plainSecret) {
            throw new \RuntimeException('Failed to decrypt TOTP secret - wrong key or corrupted data.');
        }

        return $plainSecret;
    }

    private function rawKey(): string
    {
        return sodium_hex2bin($this->platformAdminTotpEncryptionKeyHex);
    }
}
