<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestTrait;

/**
 * Jeton de récupération de compte (US-AUTH-003, `symfonycasts/reset-password-bundle`).
 * Le stockage (sélecteur + hash du jeton, jamais le jeton en clair) est imposé par le
 * bundle via ResetPasswordRequestTrait.
 */
#[ORM\Entity(repositoryClass: \App\Identity\Repository\ResetPasswordRequestRepository::class)]
#[ORM\Table(name: 'reset_password_requests')]
class ResetPasswordRequest implements ResetPasswordRequestInterface
{
    use ResetPasswordRequestTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    public function __construct(User $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->initialize($expiresAt, $selector, $hashedToken);
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
