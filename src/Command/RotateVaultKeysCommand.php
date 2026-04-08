<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\SecretRepository;
use App\Secrets\SecretPayloadCodec;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:vault:rotate-keys', description: 'Rechiffre les secrets avec la clé active du keyring.')]
final class RotateVaultKeysCommand extends Command
{
    public function __construct(
        private readonly SecretRepository $secrets,
        private readonly SecretPayloadCodec $payloadCodec,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compte les secrets à rechiffrer sans persister les changements.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $rotated = 0;
        foreach ($this->secrets->findAll() as $secret) {
            if (!$this->payloadCodec->rotate($secret)) {
                continue;
            }

            ++$rotated;
            if ($dryRun) {
                $this->em->refresh($secret);
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf('%d secret(s) %s.', $rotated, $dryRun ? 'à rechiffrer' : 'rechiffré(s)'));

        return Command::SUCCESS;
    }
}
