<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:set-role', description: 'Met a jour le role metier d\'un utilisateur')]
final class SetUserRoleCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur')
            ->addArgument('role', InputArgument::REQUIRED, 'ROLE_ADMIN, ROLE_LEAD, ROLE_EDITOR ou ROLE_USER');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $role = trim((string) $input->getArgument('role'));

        $user = $this->users->findOneBy(['email' => $email]);
        if (null === $user) {
            $io->error(sprintf('Aucun utilisateur trouve pour %s.', $email));

            return Command::FAILURE;
        }

        try {
            $user->setPrimaryRole($role);
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $this->em->flush();

        $io->success(sprintf(
            'Role %s attribue a %s.',
            $user->getPrimaryRole(),
            $user->getEmail(),
        ));

        return Command::SUCCESS;
    }
}
