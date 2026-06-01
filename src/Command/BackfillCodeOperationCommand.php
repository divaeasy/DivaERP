<?php

namespace App\Command;

use App\Service\CodeOperationMigrationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:backfill-code-operation',
    description: 'Backfill code operation and line sens on existing pieces'
)]
class BackfillCodeOperationCommand extends Command
{
    public function __construct(
        private readonly CodeOperationMigrationService $migrationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'mode',
                null,
                InputOption::VALUE_REQUIRED,
                'Mode de migration: auto (complet) ou manual (batch limite)',
                'auto'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Nombre max de lignes/pieces a traiter en mode manual',
                '200'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mode = strtolower(trim((string) $input->getOption('mode')));
        $limit = (int) $input->getOption('limit');

        if (!in_array($mode, ['auto', 'manual'], true)) {
            $output->writeln('<error>Mode invalide. Utilisez --mode=auto ou --mode=manual.</error>');

            return Command::INVALID;
        }

        $result = $mode === 'manual'
            ? $this->migrationService->runManualMigration($limit)
            : $this->migrationService->runAutomaticMigration();

        $status = $this->migrationService->getStatus();

        $output->writeln(sprintf('Mode: %s', $mode));
        $output->writeln(sprintf('Codes operations crees: %d', (int) ($result['createdOperations'] ?? 0)));
        $output->writeln(sprintf('Pieces client/prospect migrees: %d', (int) ($result['updatedClientPieces'] ?? 0)));
        $output->writeln(sprintf('Pieces fournisseur migrees: %d', (int) ($result['updatedSupplierPieces'] ?? 0)));
        $output->writeln(sprintf('Pieces internes migrees: %d', (int) ($result['updatedInternalPieces'] ?? 0)));
        $output->writeln(sprintf('Lignes sens mises a jour: %d', (int) ($result['filledLineSens'] ?? 0)));
        $output->writeln(sprintf('Pieces sans code operation restantes: %d', (int) ($status['piecesWithoutCodeOperation'] ?? 0)));
        $output->writeln(sprintf('Lignes sans sens restantes: %d', (int) ($status['linesWithoutSens'] ?? 0)));

        return Command::SUCCESS;
    }
}
