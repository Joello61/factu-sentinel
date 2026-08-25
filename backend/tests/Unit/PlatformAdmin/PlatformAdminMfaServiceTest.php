<?php

declare(strict_types=1);

namespace App\Tests\Unit\PlatformAdmin;

use App\PlatformAdmin\Service\PlatformAdminMfaService;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Test unitaire pur (aucun conteneur, aucune base de données) - plan Phase 15. Horloge
 * injectée et figée (MockClock, symfony/clock) pour un résultat déterministe : même entrée +
 * même instant = même code TOTP à chaque exécution, jamais une dépendance à l'heure système
 * réelle du poste d'exécution des tests.
 */
final class PlatformAdminMfaServiceTest extends TestCase
{
    public function testEncryptThenDecryptRoundTripsToTheOriginalSecret(): void
    {
        $service = new PlatformAdminMfaService(new MockClock(), $this->validKeyHex());

        $plainSecret = $service->generatePlainSecret();
        $encrypted = $service->encrypt($plainSecret);

        self::assertNotSame($plainSecret, $encrypted, 'Le secret ne doit jamais être stocké tel quel.');
        self::assertSame($plainSecret, $service->decrypt($encrypted));
    }

    public function testEncryptingTheSameSecretTwiceProducesDifferentCiphertexts(): void
    {
        $service = new PlatformAdminMfaService(new MockClock(), $this->validKeyHex());
        $plainSecret = $service->generatePlainSecret();

        $first = $service->encrypt($plainSecret);
        $second = $service->encrypt($plainSecret);

        self::assertNotSame($first, $second, 'Un nonce aléatoire par appel doit empêcher deux chiffrements identiques du même secret.');
        self::assertSame($plainSecret, $service->decrypt($first));
        self::assertSame($plainSecret, $service->decrypt($second));
    }

    public function testVerifyCodeAcceptsTheCurrentCodeAtAFixedInstant(): void
    {
        $clock = new MockClock('2026-08-21 12:00:00');
        $service = new PlatformAdminMfaService($clock, $this->validKeyHex());
        $plainSecret = $service->generatePlainSecret();

        $currentCode = TOTP::createFromSecret($plainSecret, $clock)->now();

        self::assertTrue($service->verifyCode($plainSecret, $currentCode));
    }

    public function testVerifyCodeRejectsAWrongCode(): void
    {
        $clock = new MockClock('2026-08-21 12:00:00');
        $service = new PlatformAdminMfaService($clock, $this->validKeyHex());
        $plainSecret = $service->generatePlainSecret();

        self::assertFalse($service->verifyCode($plainSecret, '000000'));
    }

    public function testDecryptingAMalformedPayloadThrows(): void
    {
        $service = new PlatformAdminMfaService(new MockClock(), $this->validKeyHex());

        $this->expectException(\RuntimeException::class);
        $service->decrypt(base64_encode('too-short'));
    }

    private function validKeyHex(): string
    {
        return bin2hex(random_bytes(\SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }
}
