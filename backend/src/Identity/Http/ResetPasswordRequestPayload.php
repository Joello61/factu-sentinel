<?php

declare(strict_types=1);

namespace App\Identity\Http;

use Symfony\Component\Validator\Constraints as Assert;

/** Nommée avec un suffixe "Payload" pour ne pas entrer en collision avec l'entité ResetPasswordRequest. */
final readonly class ResetPasswordRequestPayload
{
    public function __construct(
        #[Assert\NotBlank]
        public string $token,
        #[Assert\NotBlank]
        #[Assert\Length(min: 15, max: 128)]
        public string $password,
    ) {
    }
}
