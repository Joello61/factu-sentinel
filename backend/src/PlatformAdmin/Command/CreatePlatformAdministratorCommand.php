<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Command;

use App\PlatformAdmin\Entity\PlatformAdministrator;
use App\PlatformAdmin\Repository\PlatformAdministratorRepository;
use App\PlatformAdmin\Service\PlatformAdminMfaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Provisioning d'un App\PlatformAdmin\Entity\PlatformAdministrator (plan Phase 15) - aucune
 * user story ne décrit la création d'un compte par un autre PlatformAdministrator (rôle
 * unique, pas de RBAC interne au MVP de cette phase, docs/10-security-privacy.md section 17
 * bis), donc aucun endpoint API - accès opérationnel interne uniquement, même traitement que
 * /admin/rule-versions (backend/CLAUDE.md).
 *
 * N'affiche jamais le secret TOTP en clair dans un log persistant - uniquement sur la sortie
 * console interactive, à usage unique (l'opérateur l'enrôle immédiatement dans son
 * application d'authentification, puis cette sortie ne doit plus être conservée).
 */
#[AsCommand(name: 'app:platform-admin:create', description: 'Crée un compte PlatformAdministrator (accès opérationnel interne uniquement)')]
final class CreatePlatformAdministratorCommand extends Command
{
    public function __construct(
        private readonly PlatformAdministratorRepository $repository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PlatformAdminMfaService $mfaService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email du compte à créer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        if (null !== $this->repository->findOneByEmail($email)) {
            $io->error(sprintf('Un PlatformAdministrator existe déjà pour "%s".', $email));

            return Command::FAILURE;
        }

        $question = new Question('Mot de passe (min. 15 caractères) : ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $password = (string) $io->askQuestion($question);

        if (\strlen($password) < 15) {
            $io->error('Le mot de passe doit comporter au moins 15 caractères.');

            return Command::FAILURE;
        }

        $plainSecret = $this->mfaService->generatePlainSecret();

        // hashPassword() a besoin d'une instance pour résoudre le hasher configuré
        // (security.yaml, password_hashers) - même patron que
        // App\Identity\Controller\RegisterController.
        $administrator = new PlatformAdministrator($email, 'temporary', $this->mfaService->encrypt($plainSecret));
        $administrator->setPassword($this->passwordHasher->hashPassword($administrator, $password));

        $this->entityManager->persist($administrator);
        $this->entityManager->flush();

        $io->success(sprintf('Compte PlatformAdministrator créé pour "%s".', $email));
        $io->warning('Enrôlez immédiatement ce secret dans une application TOTP (Google Authenticator ou équivalent) - il ne sera plus jamais affiché.');
        $io->writeln(sprintf('URI de provisioning : %s', $this->mfaService->getProvisioningUri($plainSecret, $email)));
        $io->note('Aucun générateur de QR code n\'est intégré (dépendance jugée non nécessaire, ../CLAUDE.md section 21) - collez cette URI dans un générateur de QR code de confiance si besoin, ou saisissez le secret manuellement.');
        $io->writeln(sprintf('Le premier login (POST /api/v1/platform-admin/auth/login puis .../auth/mfa/verify avec un code TOTP valide) confirmera l\'enrôlement.'));

        return Command::SUCCESS;
    }
}
