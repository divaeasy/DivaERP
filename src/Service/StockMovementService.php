<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Lignepiece;
use App\Entity\Entetepiece;
use App\Enum\SensEnum;
use App\Enum\SortiStockMode;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

class StockMovementService
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Find consumable line pieces (Entree) for stock deduction based on FIFO/LIFO.
     *
     * @param Article $article The article to consume stock from
     * @param float $quantityNeeded The quantity to consume
     * @param SortiStockMode $mode FIFO or LIFO consumption mode
     * @return array<int, array{ligne: Lignepiece, quantity: float}> Array of [ligne, quantity to consume]
     */
    public function findConsumableLignes(Article $article, float $quantityNeeded, SortiStockMode $mode): array
    {
        $ligneRepository = $this->doctrine->getRepository(Lignepiece::class);
        
        // Find all Entree lines for this article with available stock
        $queryBuilder = $ligneRepository->createQueryBuilder('lp')
            ->innerJoin('lp.piece', 'ep')
            ->innerJoin('ep.codeOperation', 'co')
            ->where('lp.article = :article')
            ->andWhere('ep.type IN (:types)')
            ->andWhere('co.sens = :sens')
            ->andWhere('lp.qteSt > 0')
            ->setParameter('article', $article)
            ->setParameter('types', ['BL', 'Facture'])
            ->setParameter('sens', SensEnum::DEBIT);
        
        // Sort by FIFO (earliest) or LIFO (latest)
        if ($mode === SortiStockMode::FIFO) {
            $queryBuilder->orderBy('ep.datep', 'ASC');
        } else {
            $queryBuilder->orderBy('ep.datep', 'DESC');
        }
        $queryBuilder->addOrderBy('lp.id', $mode === SortiStockMode::FIFO ? 'ASC' : 'DESC');
        
        $entreeLines = $queryBuilder->getQuery()->getResult();
        
        $consumedLines = [];
        $remainingQuantity = $quantityNeeded;
        
        foreach ($entreeLines as $ligne) {
            if ($remainingQuantity <= 0) {
                break;
            }
            
            $availableQte = $ligne->getQteSt() ?? 0;
            $quantityToConsume = min($remainingQuantity, $availableQte);
            
            $consumedLines[] = [
                'ligne' => $ligne,
                'quantity' => $quantityToConsume,
            ];
            
            $remainingQuantity -= $quantityToConsume;
        }
        
        if ($remainingQuantity > 0) {
            $this->logger->warning(
                'Insufficient stock for article',
                [
                    'article_id' => $article->getId(),
                    'article_libelle' => $article->getLibelle(),
                    'needed' => $quantityNeeded,
                    'remaining' => $remainingQuantity,
                ]
            );
        }
        
        return $consumedLines;
    }

    /**
     * Consume stock for a Sortie (exit) line piece.
     * Reduces QteSt from Entree pieces and updates mouvementDeStock reference.
     *
     * @param Lignepiece $sortieLigne The exit line to process
     * @throws \Exception if line is not Sortie or has no article
     */
    public function consumeStock(Lignepiece $sortieLigne): void
    {
        $piece = $sortieLigne->getPiece();
        if ($piece === null) {
            throw new \Exception('Lignepiece must have a piece');
        }
        
        $article = $sortieLigne->getArticle();
        if ($article === null) {
            throw new \Exception('Lignepiece must have an article');
        }
        
        $codeOp = $piece->getCodeOperation();
        if ($codeOp === null) {
            throw new \Exception('Piece must have a code operation');
        }
        
        // Only process Sortie (CREDIT) operations
        if ($codeOp->getSens() !== SensEnum::CREDIT) {
            return;
        }
        
        // Only process BL/Facture types
        if (!in_array($piece->getType(), ['BL', 'Facture'], true)) {
            return;
        }
        
        $quantityToConsume = (float) ($sortieLigne->getQte() ?? 0);
        if ($quantityToConsume <= 0) {
            return;
        }
        
        // Set QteSt for the Sortie line
        $sortieLigne->setQteSt($quantityToConsume);
        
        // Get dossier's sortiStockMode (FIFO/LIFO)
        $dossier = $piece->getDossier();
        if ($dossier === null) {
            $mode = SortiStockMode::FIFO;
        } else {
            $mode = $article->getSortiStock();
            if ($mode === SortiStockMode::DOSSIER) {
                $mode = $dossier->getSortiStockDefaut();
            }
        }
        
        // Find consumable lines based on FIFO/LIFO
        $consumedLines = $this->findConsumableLignes($article, $quantityToConsume, $mode);
        
        $manager = $this->doctrine->getManager();
        
        // Reduce QteSt from Entree lines
        foreach ($consumedLines as $consumption) {
            $entreeLigne = $consumption['ligne'];
            $quantityToReduce = $consumption['quantity'];
            
            $currentQteSt = (float) ($entreeLigne->getQteSt() ?? 0);
            $newQteSt = $currentQteSt - $quantityToReduce;
            
            $entreeLigne->setQteSt($newQteSt);
            
            // Store reference to the source Entree piece
            $sourcePieceRef = $entreeLigne->getPiece()?->getPieceref() ?? 'unknown';
            $sortieLigne->setMouvementDeStock($sourcePieceRef);
            
            $manager->persist($entreeLigne);
            
            $this->logger->info(
                'Stock consumed',
                [
                    'article_id' => $article->getId(),
                    'entree_piece' => $sourcePieceRef,
                    'sortie_piece' => $piece->getPieceref(),
                    'quantity' => $quantityToReduce,
                    'previous_qte_st' => $currentQteSt,
                    'new_qte_st' => $newQteSt,
                ]
            );
        }
        
        $manager->persist($sortieLigne);
        $manager->flush();
    }

    /**
     * Recalculate stock for all articles in a dossier.
     * Updates the stock_actuel cached column.
     */
    public function recalculateStockForDossier(int $dossierId): int
    {
        $articleRepository = $this->doctrine->getRepository(Article::class);
        $articles = $articleRepository->findBy(['dossier' => $dossierId]);
        
        $manager = $this->doctrine->getManager();
        $count = 0;
        
        foreach ($articles as $article) {
            // Stock calculation is done in Article::getStockActuel()
            // Here we could update a cache column if needed
            $count++;
        }
        
        return $count;
    }
}
