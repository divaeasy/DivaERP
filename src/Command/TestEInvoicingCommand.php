<?php

namespace App\Command;

use App\Entity\Entetepiece;
use App\Service\EInvoicing\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test:e-invoicing',
    description: 'Test e-invoicing functionality with an invoice',
)]
class TestEInvoicingCommand extends Command
{
    public function __construct(
        private InvoiceService $invoiceService,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('invoiceId', InputArgument::REQUIRED, 'The invoice ID to test')
            ->addArgument('test', InputArgument::OPTIONAL, 'Test type: generate, submit, status, history (default: all)', 'all');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $invoiceId = $input->getArgument('invoiceId');
        $testType = $input->getArgument('test');

        // Get invoice
        $invoice = $this->entityManager->getRepository(Entetepiece::class)->find($invoiceId);
        if (!$invoice) {
            $output->writeln("<error>Invoice #{$invoiceId} not found</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Testing e-invoicing for Invoice #{$invoiceId}</info>");
        $output->writeln('');

        try {
            // Test 1: Generate Facture-X
            if ($testType === 'all' || $testType === 'generate') {
                $output->writeln("<fg=cyan>1. Generating Facture-X...</>");
                $factureX = $this->invoiceService->generateFactureX($invoice);

                if ($factureX['success']) {
                    $output->writeln("<fg=green>✓ Facture-X generated successfully</>");
                    $output->writeln("  - XML Size: " . strlen($factureX['xml']) . " bytes");
                    $output->writeln("  - PDF Size: " . strlen($factureX['pdf']) . " bytes");

                    // Save samples for inspection
                    file_put_contents('/tmp/invoice_' . $invoiceId . '.xml', $factureX['xml']);
                    file_put_contents('/tmp/invoice_' . $invoiceId . '.pdf', $factureX['pdf']);
                    $output->writeln("  - Files saved to /tmp/invoice_" . $invoiceId . ".xml and .pdf");
                } else {
                    $output->writeln("<fg=red>✗ Failed: " . $factureX['error'] . "</>");
                    return Command::FAILURE;
                }
                $output->writeln('');
            }

            // Test 2: Check Tiime Status
            if ($testType === 'all' || $testType === 'status') {
                $output->writeln("<fg=cyan>2. Checking Tiime Status...</>");
                if ($invoice->getTiimeInvoiceId()) {
                    $status = $this->invoiceService->checkTiimeStatus($invoice);
                    $output->writeln("<fg=green>✓ Tiime Status Check</>");
                    $output->writeln(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } else {
                    $output->writeln("<fg=yellow>⚠ No Tiime Invoice ID set (not submitted yet)</>");
                }
                $output->writeln('');
            }

            // Test 3: View Lifecycle History
            if ($testType === 'all' || $testType === 'history') {
                $output->writeln("<fg=cyan>3. Invoice Lifecycle History</>");
                $history = $this->invoiceService->getInvoiceHistory($invoice);

                if (empty($history)) {
                    $output->writeln("<fg=yellow>No status history recorded yet</>");
                } else {
                    foreach ($history as $event) {
                        $output->writeln("<fg=blue>• " . $event['status'] . "</> - " . $event['timestamp']);
                        if ($event['details']) {
                            $output->writeln("  Details: " . json_encode($event['details']));
                        }
                    }
                }
                $output->writeln('');
            }

            // Test 4: Submit to Tiime (only if credentials configured)
            if ($testType === 'submit') {
                $output->writeln("<fg=cyan>4. Submitting to Tiime PDP...</>");
                $output->writeln("<fg=yellow>Note: This requires valid Tiime API credentials in .env</>");

                $result = $this->invoiceService->processInvoice($invoice);

                if ($result['success']) {
                    $output->writeln("<fg=green>✓ Submitted to Tiime</>");
                    $output->writeln("  - Tiime Invoice ID: " . $result['tiime_invoice_id']);
                    $output->writeln("  - Submission ID: " . $result['submission_id']);
                } else {
                    $output->writeln("<fg=red>✗ Submission failed: " . $result['error'] . "</>");
                }
                $output->writeln('');
            }

            // Summary
            $output->writeln("<fg=green>Testing complete!</>");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>Exception: " . $e->getMessage() . "</error>");
            $output->writeln("<error>" . $e->getTraceAsString() . "</error>");
            return Command::FAILURE;
        }
    }
}
