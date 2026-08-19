<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\Membership;
use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use App\Identity\Http\RegisterRequest;
use App\Identity\Mailer\VerifyEmailMailer;
use App\Identity\Repository\UserRepository;
use App\Organization\Entity\Organization;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * US-AUTH-001 : crée le compte, une Organization vide, et le Membership(OWNER) qui les
 * relie — en une seule transaction (invariant Phase 2, voir plan). Ne connecte pas
 * automatiquement l'utilisateur (pas de jeton renvoyé ici) : le frontend enchaîne
 * immédiatement avec POST /auth/login, ce qui évite de dupliquer la logique d'émission de
 * jeton déjà gérée par le firewall (json_login) plutôt que de la reproduire manuellement
 * ici (voir plan Phase 2).
 */
final class RegisterController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly VerifyEmailMailer $verifyEmailMailer,
    ) {
    }

    #[Route('/api/v1/auth/register', name: 'auth_register', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] RegisterRequest $payload): JsonResponse
    {
        if (null !== $this->userRepository->findOneByEmail($payload->email)) {
            // Cohérent avec US-AUTH-002 : pas de message distinguant les cas, mais ici
            // l'unicité de l'email doit malgré tout être signalée pour que l'utilisateur
            // sache pourquoi son inscription échoue (contrainte différente de la connexion).
            throw new ConflictHttpException('Un compte existe déjà avec cet email.');
        }

        // hashPassword() a besoin d'une instance de User pour résoudre le hasher configuré
        // (App\Identity\Entity\User => "auto"), d'où le mot de passe temporaire remplacé
        // aussitôt par le hash réel.
        $user = new User($payload->email, 'temporary');
        $user->setPassword($this->passwordHasher->hashPassword($user, $payload->password));

        $organization = new Organization();
        $membership = new Membership($user, $organization, Role::OWNER);

        $this->entityManager->persist($organization);
        $this->entityManager->persist($user);
        $this->entityManager->persist($membership);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Fenêtre de course entre la vérification ci-dessus et le flush.
            throw new ConflictHttpException('Un compte existe déjà avec cet email.');
        }

        $this->verifyEmailMailer->send($user);

        return new JsonResponse(
            ['data' => ['id' => $user->getId()->toRfc4122(), 'email' => $user->getEmail()]],
            JsonResponse::HTTP_CREATED,
        );
    }
}
