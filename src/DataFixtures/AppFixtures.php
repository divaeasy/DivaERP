<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\Clients;
use App\Entity\Dossier;
use App\Entity\Entetepiece;
use App\Entity\Lignepiece;
use App\Entity\Reglement;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use DateTime;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create Dossiers (Companies/Categories)
        $dossiers = [];
        $dossierNames = ['Électronique', 'Mobilier', 'Services', 'Logiciels', 'Consulting'];
        
        foreach ($dossierNames as $name) {
            $dossier = new Dossier();
            $dossier->setNom($name);
            $dossier->setAdresse('123 Rue de ' . $name);
            $dossier->setRc('RC' . rand(1000, 9999));
            $dossier->setPenalitesretard('Penalites de retard applicables conformement a la loi 2008-776 du 4 aout 2008. Indemnite forfaitaire pour frais de recouvrement : 40 EUR.');
            $manager->persist($dossier);
            $dossiers[] = $dossier;
        }
        $manager->flush();

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
            $manager->persist($client);
            $clients[] = $client;
        }
        $manager->flush();

        // Create Articles (Products)
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
            $manager->persist($article);
            $articles[$name] = [$article, $qty];
        }
        $manager->flush();

        // Create Reglement types
        $reglement = new Reglement();
        $reglement->setLibelle('Virement');
        $reglement->setEcheance(30);
        $manager->persist($reglement);
        $manager->flush();

        // Create Invoices for 2025 and 2026
        $months = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
        $years = [2025, 2026];

        foreach ($years as $year) {
            foreach ($months as $month) {
                // 3-5 invoices per month
                $invoiceCount = rand(3, 5);
                
                for ($i = 0; $i < $invoiceCount; $i++) {
                    $day = rand(1, 28);
                    $invoiceDate = new DateTime(sprintf("%d-%02d-%02d", $year, $month, $day));
                    
                    $invoice = new Entetepiece();
                    $invoice->setType('Facture');
                    $invoice->setTypet('VAT');
                    $invoice->setClient($clients[array_rand($clients)]);
                    $invoice->setPieceno(rand(1000, 9999));
                    $invoice->setPieceref('INV-' . $year . '-' . rand(1000, 9999));
                    $invoice->setDossier($dossiers[array_rand($dossiers)]);
                    $invoice->setDatep($invoiceDate);
                    $invoice->setReglement($reglement);
                    $invoice->setStatut('Validée');
                    
                    // Random total amount
                    $totalAmount = rand(500, 5000);
                    $invoice->setMontant($totalAmount);
                    
                    // Set due date 30 days after invoice date
                    $dueDate = clone $invoiceDate;
                    $dueDate->modify('+30 days');
                    $invoice->setDelai($dueDate);
                    
                    $manager->persist($invoice);

                    // Add 2-4 line items
                    $lineCount = rand(2, 4);
                    $remainingAmount = $totalAmount;

                    foreach ($articles as $productName => [$article, $maxQty]) {
                        if ($lineCount <= 0) break;

                        $line = new Lignepiece();
                        $line->setArticle($article);
                        $line->setPiece($invoice);
                        $line->setDossier($invoice->getDossier());
                        
                        $quantity = rand(1, min(5, $maxQty));
                        $unitPrice = $remainingAmount / $lineCount / $quantity;
                        $lineAmount = $quantity * $unitPrice;
                        
                        $line->setQte($quantity);
                        $line->setPub($unitPrice);
                        $line->setMontant($lineAmount);
                        
                        $manager->persist($line);
                        $lineCount--;
                        $remainingAmount -= $lineAmount;
                    }
                }
            }
        }

        $manager->flush();
    }
}
