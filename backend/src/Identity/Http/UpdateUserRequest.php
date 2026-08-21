<?php

declare(strict_types=1);

namespace App\Identity\Http;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH /users/current (US-SETTINGS-001, docs/08-api-specification.md section 23, voir plan
 * Phase 13). `current_password` est requis dès que `email` ou `new_password` est fourni -
 * vérifié dans App\Identity\Service\UpdateCurrentUserService, pas ici : la correspondance
 * avec le mot de passe réel du compte dépend de l'entité chargée depuis la base, hors de
 * portée d'une contrainte déclarative sur ce DTO seul.
 */
final readonly class UpdateUserRequest
{
    public function __construct(
        #[Assert\Email(message: 'Adresse email invalide.')]
        public ?string $email = null,
        #[SerializedName('current_password')]
        public ?string $currentPassword = null,
        #[SerializedName('new_password')]
        #[Assert\Length(min: 15, max: 128, minMessage: 'Le nouveau mot de passe doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le nouveau mot de passe doit contenir au plus {{ limit }} caractères.')]
        public ?string $newPassword = null,
    ) {
    }
}
