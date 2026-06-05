<?php

namespace App\EventListener;

use App\Entity\Lignepiece;
use App\Enum\SensEnum;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Psr\Log\LoggerInterface;

#[AsEntityListener(event: 'prePersist', method: 'prePersist', entity: Lignepiece::class)]
#[AsEntityListener(event: 'preUpdate', method: 'preUpdate', entity: Lignepiece::class)]
class LignepieceStockEventListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Initialize stock quantity before a Lignepiece is inserted.
     */
    public function prePersist(Lignepiece $ligne, PrePersistEventArgs $args): void
    {
        $this->initializeEntryStockQuantity($ligne);
    }

    /**
     * Initialize stock quantity before a Lignepiece is updated.
     */
    public function preUpdate(Lignepiece $ligne, PreUpdateEventArgs $args): void
    {
        $this->initializeEntryStockQuantity($ligne, $args);
    }

    /**
     * Auto-populate QteSt for Entree pieces.
     */
    private function initializeEntryStockQuantity(Lignepiece $ligne, ?PreUpdateEventArgs $updateArgs = null): void
    {
        try {
            $piece = $ligne->getPiece();
            if ($piece === null) {
                return;
            }
            
            $codeOp = $piece->getCodeOperation();
            if ($codeOp === null) {
                return;
            }
            
            // Only process BL/Facture pieces
            $pieceType = $piece->getType();
            if (!in_array($pieceType, ['BL', 'Facture'], true)) {
                return;
            }
            
            if ($codeOp->getSens() !== SensEnum::DEBIT) {
                return;
            }

            if ($ligne->getQteSt() === null) {
                $ligne->setQteSt((float) ($ligne->getQte() ?? 0));
                $this->logger->debug(
                    'Auto-populated QteSt for Entree ligne',
                    [
                        'ligne_id' => $ligne->getId(),
                        'qte' => $ligne->getQte(),
                        'piece_ref' => $piece->getPieceref(),
                    ]
                );
            }

            if ($updateArgs !== null && $updateArgs->hasChangedField('qte')) {
                $oldQte = (float) $updateArgs->getOldValue('qte');
                $newQte = (float) $updateArgs->getNewValue('qte');
                $adjustedQteSt = max(0.0, (float) ($ligne->getQteSt() ?? 0) + ($newQte - $oldQte));
                $ligne->setQteSt($adjustedQteSt);
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'Error while initializing Lignepiece stock quantity',
                [
                    'exception' => $e->getMessage(),
                    'ligne_id' => $ligne->getId() ?? 'new',
                ]
            );
        }
    }
}
