<?php

declare(strict_types=1);

namespace App\Identity\Http;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Longueur mot de passe : NIST 2026 recommande 15 caractères minimum pour une
 * authentification à un seul facteur (MFA hors MVP, docs/10-security-privacy.md section
 * 12), sans règle de complexité forcée. Plafond 128 : anti-DoS sur le hachage, pas une
 * limite d'algorithme (voir plan Phase 2).
 */
final readonly class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
        #[Assert\NotBlank]
        #[Assert\Length(min: 15, max: 128)]
        public string $password,
    ) {
    }
}
