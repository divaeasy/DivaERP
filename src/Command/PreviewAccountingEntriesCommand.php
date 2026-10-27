<?php

namespace App\Command;

use App\Entity\Entetepiece;
use App\Service\AccountingEntryGeneratorService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:preview-accounting-entries',
    description: 'Preview accounting entries generated for a piece'
)]
class PreviewAccountingEntriesCommand extends Command
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly AccountingEntryGeneratorService $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('pieceId', InputArgument::REQUIRED, 'Piece ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pieceId = (int) $input->getArgument('pieceId');
        if ($pieceId <= 0) {
            $output->writeln('<error>ID de piece invalide.</error>');

            return Command::INVALID;
        }

        $piece = $this->doctrine->getRepository(Entetepiece::class)->find($pieceId);
        if (!$piece instanceof Entetepiece) {
            $output->writeln('<error>Piece introuvable.</error>');

            return Command::FAILURE;
        }

        $entries = $this->generator->generateForPiece($piece);
        if ($entries === []) {
            $output->writeln('<comment>Aucune ecriture generee.</comment>');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Piece #%d - %d ecriture(s)', (int) $piece->getId(), count($entries)));
        foreach ($entries as $entry) {
            $output->writeln(sprintf(
                '%s | Debit: %.2f | Credit: %.2f | Sens: %s | CodeOp: %s',
                (string) ($entry['libelle'] ?? ''),
                (float) ($entry['debit'] ?? 0),
                (float) ($entry['credit'] ?? 0),
                (string) ($entry['sens'] ?? ''),
                (string) ($entry['codeOperation'] ?? '')
            ));
        }

        return Command::SUCCESS;
    }
}
