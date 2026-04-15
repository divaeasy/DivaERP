<?php

namespace App\Service;

use App\Entity\Entetepiece;
use App\Entity\Lignepiece;
use Doctrine\Persistence\ManagerRegistry;

class PieceTransitionService
{
    // Define the workflow chain
    private const PIECE_WORKFLOW = [
        'Devis' => 'Commande',
        'Commande' => 'BL',
        'BL' => 'Facture',
        'Facture' => null, // Terminal state
    ];

    public function __construct(private ManagerRegistry $doctrine) {}

    /**
     * Get available transition types for current piece type
     * Returns all possible next types, not just immediate next
     */
    public function getAvailableTransitionTypes(string $currentType): array
    {
        return match($currentType) {
            'Devis' => ['Commande', 'BL', 'Facture'],
            'Commande' => ['BL', 'Facture'],
            'BL' => ['Facture'],
            'Facture' => [],
            default => [],
        };
    }

    /**
     * Get the next piece type in the workflow (immediate next only)
     */
    public function getNextPieceType(string $currentType): ?string
    {
        return self::PIECE_WORKFLOW[$currentType] ?? null;
    }

    /**
     * Check if a piece can be transitioned
     */
    public function canTransitionPiece(Entetepiece $piece): bool
    {
        // 1. Must have a next type in workflow
        if (empty($this->getAvailableTransitionTypes($piece->getType()))) {
            return false;
        }

        // 2. Must be validated
        if ($piece->getStatut() !== 'Validée') {
            return false;
        }

        // 3. Must have at least one line
        if ($piece->getLignepieces()->count() === 0) {
            return false;
        }

        // 4. Check if all lines are complete
        foreach ($piece->getLignepieces() as $ligne) {
            if (!$ligne->getArticle() || !$ligne->getQuantite() || !$ligne->getPrix()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get reason why transition is blocked
     */
    public function getTransitionBlockReason(Entetepiece $piece): ?string
    {
        if (empty($this->getAvailableTransitionTypes($piece->getType()))) {
            return 'Cette pièce est au dernier stade du processus';
        }

        if ($piece->getStatut() !== 'Validée') {
            return 'La pièce doit être validée avant la conversion';
        }

        if ($piece->getLignepieces()->count() === 0) {
            return 'La pièce doit contenir au moins une ligne';
        }

        foreach ($piece->getLignepieces() as $ligne) {
            if (!$ligne->getArticle()) {
                return 'Toutes les lignes doivent avoir un article';
            }
            if (!$ligne->getQuantite()) {
                return 'Toutes les lignes doivent avoir une quantité';
            }
            if (!$ligne->getPrix()) {
                return 'Toutes les lignes doivent avoir un prix';
            }
        }

        return null;
    }

    /**
     * Transition a piece to the next type
     */
    public function transitionPiece(Entetepiece $piece, string $nextType): Entetepiece
    {
        if (!$this->canTransitionPiece($piece)) {
            throw new \LogicException('Cannot transition this piece. ' . ($this->getTransitionBlockReason($piece) ?? 'Unknown reason'));
        }

        // Validate that nextType is available for this piece
        $availableTypes = $this->getAvailableTransitionTypes($piece->getType());
        if (!in_array($nextType, $availableTypes, true)) {
            throw new \LogicException("Cannot transition to type '{$nextType}' from '{$piece->getType()}'");
        }

        // Clone the piece with new type
        $newPiece = new Entetepiece();
        $newPiece->setType($nextType);
        $newPiece->setTypet($piece->getTypet());
        $newPiece->setTierId($piece->getTierId());
        $newPiece->setDevise($piece->getDevise());
        $newPiece->setReglement($piece->getReglement());
        $newPiece->setDossier($piece->getDossier());
        $newPiece->setDatep(new \DateTime());
        $newPiece->setStatut('Brouillon'); // Start as draft
        $newPiece->setRemise($piece->getRemise());

        // Copy all line items
        foreach ($piece->getLignepieces() as $ligne) {
            $newLigne = new Lignepiece();
            $newLigne->setArticle($ligne->getArticle());
            $newLigne->setQuantite($ligne->getQuantite());
            $newLigne->setPrix($ligne->getPrix());
            $newLigne->setRemise($ligne->getRemise());
            $newLigne->setMontant($ligne->getMontant());
            $newLigne->setTVA($ligne->getTVA());
            $newLigne->setPiece($newPiece);
            $newPiece->addLignepiece($newLigne);
        }

        // Generate new piece number
        $this->generateNextPieceNumber($newPiece);

        // Archive the original piece
        $piece->setStatut('Perimée');

        return $newPiece;
    }

    /**
     * Auto-generate next piece number for the dossier
     */
    private function generateNextPieceNumber(Entetepiece $piece): void
    {
        $dossier = $piece->getDossier();
        if (!$dossier) {
            return;
        }

        $typeField = match($piece->getType()) {
            'Devis' => 'devisno',
            'Commande' => 'cmdno',
            'BL' => 'blno',
            'Facture' => 'factureno',
            default => null,
        };

        if ($typeField) {
            $getter = 'get' . str_replace('no', 'No', ucfirst($typeField));
            $setter = 'set' . str_replace('no', 'No', ucfirst($typeField));

            if (method_exists($dossier, $getter) && method_exists($dossier, $setter)) {
                $currentNo = $dossier->$getter() ?? 0;
                $piece->setPieceno($currentNo + 1);
                $dossier->$setter($currentNo + 1);
            }
        }
    }

    /**
     * Check if piece is archived (Perimée status)
     */
    public function isPieceArchived(Entetepiece $piece): bool
    {
        return $piece->getStatut() === 'Perimée';
    }
}
