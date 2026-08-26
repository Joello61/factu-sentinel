<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Http;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class PlatformAdminMfaVerifyRequest
{
    public function __construct(
        #[SerializedName('mfa_challenge')]
        #[Assert\NotBlank]
        public string $mfaChallenge,
        #[Assert\NotBlank]
        #[Assert\Length(min: 6, max: 6, exactMessage: 'Le code doit comporter exactement 6 chiffres.')]
        public string $code,
    ) {
    }
}
