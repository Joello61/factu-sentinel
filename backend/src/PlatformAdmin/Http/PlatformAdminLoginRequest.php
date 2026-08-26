<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PlatformAdminLoginRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
        #[Assert\NotBlank]
        public string $password,
    ) {
    }
}
