<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\Clients;
use App\Entity\Dossier;
use App\Entity\Entetepiece;
use App\Entity\Lignepiece;
use App\Entity\Reglement;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:load-test-data',
    description: 'Load test data for dashboard testing'
)]
class LoadTestDataCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Loading test data...');

        try {
            // Clear existing data
            $this->entityManager->getConnection()->executeStatement('DELETE FROM lignepiece');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM entetepiece');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM article');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM clients');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM dossier');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM reglement');

            // Create Dossiers
            $dossiers = [];
            $dossierNames = ['Électronique', 'Mobilier', 'Services', 'Logiciels', 'Consulting'];
            
            foreach ($dossierNames as $name) {
                $dossier = new Dossier();
                $dossier->setNom($name);
                $dossier->setAdresse('123 Rue de ' . $name);
                $dossier->setRc('RC' . rand(1000, 9999));
                $this->entityManager->persist($dossier);
                $dossiers[] = $dossier;
            }
            $this->entityManager->flush();
            $output->writeln('✓ Created 5 dossiers');

            // Create Clients
            $clients = [];
            $clientNames = [
                'Client A', 'Client B', 'Client C', 'Client D', 'Client E',
                'Client F', 'Client G', 'Client H', 'Client I', 'Client J'
            ];

            foreach ($clientNames as $name) {
                $client = new Clients();
                $client->setNom($name);
                $client->setDossier($dossiers[array_rand($dossiers)]);
                $client->setRue(rand(1, 500) . ' Avenue ' . $name);
                $client->setAdr1('Address Line 1');
                $client->setAdr2('Address Line 2');
                $client->setCodepostal(75000 + rand(0, 999));
                $client->setTel('06' . rand(10000000, 99999999));
                $client->setEmail(strtolower(str_replace(' ', '.', $name)) . '@example.com');
                $client->setWeb('https://www.example.com');
                $client->setLinkedin('https://linkedin.com');
                $this->entityManager->persist($client);
                $clients[] = $client;
            }
            $this->entityManager->flush();
            $output->writeln('✓ Created 10 clients');

            // Create Articles
            $articles = [];
            $productNames = [
                'Laptop Pro' => 100,
                'Desktop Computer' => 80,
                'Monitor 27"' => 120,
                'Keyboard Mechanical' => 50,
                'Mouse Wireless' => 30,
                'Office Chair' => 200,
                'Desk Wooden' => 150,
                'Lamp LED' => 40,
                'Consultation Hours' => 75,
                'Support Package' => 500,
            ];

            foreach ($productNames as $name => $qty) {
                $article = new Article();
                $article->setLibelle($name);
                $article->setDossier($dossiers[array_rand($dossiers)]);
                $this->entityManager->persist($article);
                $articles[$name] = [$article, $qty];
            }
            $this->entityManager->flush();
            $output->writeln('✓ Created 10 articles');

            // Create Reglement
            $reglement = new Reglement();
            $reglement->setLibelle('Virement');
            $reglement->setEcheance(30);
            $this->entityManager->persist($reglement);
            $this->entityManager->flush();
            $output->writeln('✓ Created reglement type');

            // Create Invoices for 2025 and 2026
            $months = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
            $years = [2025, 2026];
            $invoiceCount = 0;

            foreach ($years as $year) {
                foreach ($months as $month) {
                    $invoicesPerMonth = rand(3, 5);
                    
                    for ($i = 0; $i < $invoicesPerMonth; $i++) {
                        $day = rand(1, 28);
                        $invoiceDate = new DateTime(sprintf('%d-%02d-%02d', $year, $month, $day));
                        
                        $invoice = new Entetepiece();
                        $invoice->setType('Facture');
                        $invoice->setTypet('VAT');
                        $invoice->setClient($clients[array_rand($clients)]);
                        $invoice->setPieceno(rand(1000, 9999));
                        $invoice->setPieceref('INV-' . $year . '-' . rand(10000, 99999));
                        $invoice->setDossier($dossiers[array_rand($dossiers)]);
                        $invoice->setDatep($invoiceDate);
                        $invoice->setReglement($reglement);
                        $invoice->setStatut('Validée');
                        
                        $totalAmount = rand(500, 5000);
                        $invoice->setMontant($totalAmount);
                        
                        $dueDate = clone $invoiceDate;
                        $dueDate->modify('+30 days');
                        $invoice->setDelai($dueDate);
                        
                        $this->entityManager->persist($invoice);

                        // Add line items
                        $lineCount = rand(2, 4);
                        foreach (array_keys($articles) as $productName) {
                            if ($lineCount <= 0) break;
                            
                            list($article, $maxQty) = $articles[$productName];
                            
                            $line = new Lignepiece();
                            $line->setArticle($article);
                            $line->setPiece($invoice);
                            $line->setDossier($invoice->getDossier());
                            
                            $quantity = rand(1, 5);
                            $unitPrice = $totalAmount / $lineCount / $quantity;
                            
                            $line->setQte($quantity);
                            $line->setPub($unitPrice);
                            $line->setMontant($quantity * $unitPrice);
                            
                            $this->entityManager->persist($line);
                            $lineCount--;
                        }
                        
                        $invoiceCount++;
                    }
                }
            }

            $this->entityManager->flush();
            $output->writeln("✓ Created {$invoiceCount} invoices with line items");
            $output->writeln("\n✅ Test data loaded successfully!");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("\n❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
