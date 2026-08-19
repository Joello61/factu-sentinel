<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\FamilyAwareRefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenFamilyTrait;

/**
 * Table de gestion des refresh tokens (docs/06-technical-architecture.md, ADR-007 ;
 * `gesdinet/jwt-refresh-token-bundle`). `AbstractRefreshToken` est une classe PHP pure,
 * sans mapping Doctrine : c'est l'application qui fournit le mapping, ici en attributs
 * pour rester cohérent avec le reste du projet (docs/07-data-model.md, section 2) plutôt
 * que d'importer le mapping XML du bundle. Entité d'infrastructure vendor, pas une entité
 * du domaine : clé technique entière auto-incrémentée (pas d'UUID v7), cohérent avec le
 * choix du bundle plutôt qu'avec la convention `07-data-model.md` section 32, qui ne
 * s'applique qu'aux entités du domaine.
 *
 * `RefreshTokenFamilyTrait` porte le "family" nécessaire à `reuse_detection` (config
 * gesdinet_jwt_refresh_token.yaml) : un refresh token déjà consommé, présenté à nouveau,
 * révoque toute la famille plutôt que d'être simplement refusé.
 */
#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends AbstractRefreshToken implements FamilyAwareRefreshTokenInterface
{
    use RefreshTokenFamilyTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    protected int|string|null $id = null;

    #[ORM\Column(name: 'refresh_token', type: Types::STRING, length: 128, unique: true)]
    protected ?string $refreshToken = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected ?string $username = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    protected ?\DateTimeInterface $valid = null;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    protected ?string $family = null;

    #[ORM\Column(name: 'family_valid', type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $familyValid = null;
}
