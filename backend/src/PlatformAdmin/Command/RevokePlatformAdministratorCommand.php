<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Command;

use App\PlatformAdmin\Repository\PlatformAdministratorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Révoque un App\PlatformAdmin\Entity\PlatformAdministrator (plan Phase 15, revue
 * utilisateur du 21/08/2026) - un JWT signé valide ne doit jamais suffire à lui seul :
 * App\PlatformAdmin\Repository\PlatformAdministratorRepository::loadUserByIdentifier()
 * exclut les comptes révoqués, rechargés à chaque requête authentifiée par le firewall JWT
 * stateless - effet immédiat, dès la requête suivante, même avec un JWT non expiré.
 */
#[AsCommand(name: 'app:platform-admin:revoke', description: 'Révoque un compte PlatformAdministrator')]
final class RevokePlatformAdministratorCommand extends Command
{
    public function __construct(
        private readonly PlatformAdministratorRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email du compte à révoquer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $administrator = $this->repository->findOneByEmail($email);
        if (null === $administrator) {
            $io->error(sprintf('Aucun PlatformAdministrator actif pour "%s".', $email));

            return Command::FAILURE;
        }

        $administrator->revoke();
        $this->entityManager->flush();

        $io->success(sprintf('Compte PlatformAdministrator révoqué pour "%s".', $email));

        return Command::SUCCESS;
    }
}
