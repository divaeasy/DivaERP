<?php

namespace App\Entity;

use App\Enum\SensEnum;
use App\Repository\LignepieceRepository;
use App\Traits\TimeStampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LignepieceRepository::class)]
#[ORM\HasLifecycleCallbacks()]
class Lignepiece
{
    use TimeStampTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignepieces')]
    private ?Entetepiece $piece = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Article $article = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $designation = null;

    #[ORM\Column]
    private ?float $qte = null;

    #[ORM\Column]
    private ?float $pub = null;

    #[ORM\Column]
    private ?float $montant = null;

    #[ORM\Column(nullable: true)]
    private ?float $remise = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dossier $dossier = null;

    #[ORM\Column(length: 20, enumType: SensEnum::class, nullable: true)]
    private ?SensEnum $sens = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDossier(): ?Dossier
    {
        return $this->dossier;
    }

    public function setDossier(?Dossier $dossier): static
    {
        $this->dossier = $dossier;

        return $this;
    }

    public function getPiece(): ?Entetepiece
    {
        return $this->piece;
    }

    public function setPiece(?Entetepiece $piece): static
    {
        $this->piece = $piece;

        if ($this->sens === null && $piece?->getCodeOperation() !== null) {
            $this->sens = $piece->getCodeOperation()->getSens();
        }

        return $this;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;

        return $this;
    }

    public function getQte(): ?float
    {
        return $this->qte;
    }

    public function setQte(float $qte): static
    {
        $this->qte = $qte;

        return $this;
    }

    public function getPub(): ?float
    {
        return $this->pub;
    }

    public function setPub(float $pub): static
    {
        $this->pub = $pub;

        return $this;
    }

    public function getMontant(): ?float
    {
        return $this->montant;
    }

    public function setMontant(float $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getRemise(): ?float
    {
        return $this->remise;
    }

    public function setRemise(?float $remise): static
    {
        $this->remise = $remise;

        return $this;
    }

    public function getDesignation(): ?string
    {
        return $this->designation ?? ($this->article?->getLibelle());
    }

    public function setDesignation(?string $designation): static
    {
        $this->designation = $designation;

        return $this;
    }

    // Alias methods for compatibility
    public function getQuantite(): ?float
    {
        return $this->qte;
    }

    public function getPu(): ?float
    {
        return $this->pub;
    }

    public function getSens(): ?SensEnum
    {
        return $this->sens;
    }

    public function setSens(?SensEnum $sens): static
    {
        $this->sens = $sens;

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function syncSensFromPiece(): void
    {
        if ($this->sens !== null) {
            return;
        }

        $pieceOperation = $this->piece?->getCodeOperation();
        if ($pieceOperation !== null) {
            $this->sens = $pieceOperation->getSens();
        }
    }
}
