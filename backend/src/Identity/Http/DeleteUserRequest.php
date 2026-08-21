<?php

declare(strict_types=1);

namespace App\Identity\Http;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DELETE /users/current (US-SETTINGS-002, docs/08-api-specification.md section 23, voir plan
 * Phase 13). `current_password` requis : défense en profondeur contre un jeton d'accès
 * compromis qui supprimerait le compte en une seule requête (voir plan Phase 13 - décision
 * ajoutée, non explicitement exigée par docs/10-security-privacy.md section 39).
 */
final readonly class DeleteUserRequest
{
    public function __construct(
        #[SerializedName('current_password')]
        #[Assert\NotBlank(message: 'Le mot de passe actuel est requis pour confirmer la suppression du compte.')]
        public string $currentPassword,
    ) {
    }
}
