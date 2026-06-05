<?php

namespace App\Entity;

use App\Enum\ArticleModeGestion;
use App\Enum\ArticleModeSuivi;
use App\Enum\SortiStockMode;
use App\Repository\ArticleRepository;
use App\Traits\TimeStampTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\HasLifecycleCallbacks()]
#[ORM\Index(columns: ['libelle'])]
class Article
{

    use TimeStampTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

   

    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[ORM\ManyToOne]
    private ?Unite $unite = null;

    #[ORM\ManyToOne]
    private ?Tarifs $tarif = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dossier $dossier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 20, enumType: ArticleModeGestion::class, options: ['default' => 'En stock'])]
    private ArticleModeGestion $modeGestion = ArticleModeGestion::EN_STOCK;

    #[ORM\Column(length: 30, enumType: ArticleModeSuivi::class, options: ['default' => 'En quantité'])]
    private ArticleModeSuivi $modeSuivi = ArticleModeSuivi::EN_QUANTITE;

    #[ORM\ManyToOne]
    private ?NatureProduction $natureProduction = null;

    #[ORM\Column(length: 20, enumType: SortiStockMode::class, options: ['default' => 'FIFO'])]
    private SortiStockMode $sortiStock = SortiStockMode::FIFO;

    #[ORM\ManyToOne]
    private ?Fournisseur $fournisseurHabituel = null;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: Lignepiece::class)]
    private Collection $lignepieces;

    public function __construct()
    {
        $this->lignepieces = new ArrayCollection();
    }

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

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getUnite(): ?Unite
    {
        return $this->unite;
    }

    public function setUnite(?Unite $unite): static
    {
        $this->unite = $unite;

        return $this;
    }

    public function getTarif(): ?Tarifs
    {
        return $this->tarif;
    }

    public function setTarif(?Tarifs $tarif): static
    {
        $this->tarif = $tarif;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getModeGestion(): ArticleModeGestion
    {
        return $this->modeGestion;
    }

    public function setModeGestion(ArticleModeGestion $modeGestion): static
    {
        $this->modeGestion = $modeGestion;

        return $this;
    }

    public function getModeSuivi(): ArticleModeSuivi
    {
        return $this->modeSuivi;
    }

    public function setModeSuivi(ArticleModeSuivi $modeSuivi): static
    {
        $this->modeSuivi = $modeSuivi;

        return $this;
    }

    public function getNatureProduction(): ?NatureProduction
    {
        return $this->natureProduction;
    }

    public function setNatureProduction(?NatureProduction $natureProduction): static
    {
        $this->natureProduction = $natureProduction;

        return $this;
    }

    public function getSortiStock(): SortiStockMode
    {
        return $this->sortiStock;
    }

    public function setSortiStock(SortiStockMode $sortiStock): static
    {
        $this->sortiStock = $sortiStock;

        return $this;
    }

    public function getFournisseurHabituel(): ?Fournisseur
    {
        return $this->fournisseurHabituel;
    }

    public function setFournisseurHabituel(?Fournisseur $fournisseurHabituel): static
    {
        $this->fournisseurHabituel = $fournisseurHabituel;

        return $this;
    }

    public function getLignepieces(): Collection
    {
        return $this->lignepieces;
    }

    public function addLignepiece(Lignepiece $lignepiece): static
    {
        if (!$this->lignepieces->contains($lignepiece)) {
            $this->lignepieces->add($lignepiece);
            $lignepiece->setArticle($this);
        }

        return $this;
    }

    public function removeLignepiece(Lignepiece $lignepiece): static
    {
        $this->lignepieces->removeElement($lignepiece);

        return $this;
    }

    /**
     * Calculate current stock (QteSt sum - Sortie sum).
     * This is a transient calculation, not persisted.
     */
    public function getStockActuel(): float
    {
        $stock = 0.0;
        
        foreach ($this->lignepieces as $ligne) {
            $piece = $ligne->getPiece();
            if ($piece === null) {
                continue;
            }
            
            $codeOp = $piece->getCodeOperation();
            if ($codeOp === null) {
                continue;
            }
            
            $qteSt = $ligne->getQteSt();
            if ($qteSt === null) {
                continue;
            }
            
            // Add for Entree (DEBIT), subtract for Sortie (CREDIT)
            if ($codeOp->getSens()->value === 'Entree') {
                $stock += $qteSt;
            } else {
                $stock -= $qteSt;
            }
        }
        
        return $stock;
    }

    public function __toString()
    {
        return $this->getLibelle();
    }
}
