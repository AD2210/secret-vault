<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Crée un utilisateur coffre-fort')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('first-name', InputArgument::REQUIRED)
            ->addArgument('last-name', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED)
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Rôle métier (ROLE_ADMIN, ROLE_LEAD, ROLE_EDITOR, ROLE_USER)', User::ROLE_USER)
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Attribue ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));

        if (null !== $this->users->findOneBy(['email' => $email])) {
            $io->error(sprintf('Un utilisateur existe déjà pour %s.', $email));

            return Command::FAILURE;
        }

        $user = new User(
            $email,
            (string) $input->getArgument('first-name'),
            (string) $input->getArgument('last-name'),
        );
        $role = $input->getOption('admin') ? User::ROLE_ADMIN : (string) $input->getOption('role');
        $user->setPrimaryRole($role);
        $user->setPassword($this->hasher->hashPassword($user, (string) $input->getArgument('password')));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Utilisateur %s créé. Il devra configurer son 2FA à la première connexion.', $email));

        return Command::SUCCESS;
    }
}
