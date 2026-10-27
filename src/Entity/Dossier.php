<?php

namespace App\Entity;

use App\Enum\SortiStockMode;
use App\Repository\DossierRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DossierRepository::class)]
class Dossier
{

    
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $codepostal = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $pays = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\ManyToOne(targetEntity: Theme::class)]
    #[ORM\JoinColumn(name: 'theme_id', nullable: true, onDelete: 'SET NULL')]
    private Theme|string|null $theme = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rc = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $siret = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $naf = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tvaintra = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $tel = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $iban = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $bic = null;

    #[ORM\ManyToOne]
    private ?Devises $devise = null;

    #[ORM\Column(nullable: true)]
    private ?int $factureno = null;

    #[ORM\Column(nullable: true)]
    private ?int $devisno = null;

    #[ORM\Column(nullable: true)]
    private ?int $cmdno = null;

    #[ORM\Column(nullable: true)]
    private ?int $blno = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $penalitesretard = null;

    #[ORM\ManyToOne]
    private ?NatureProduction $natureStock = null;

    #[ORM\Column(length: 20, enumType: SortiStockMode::class, options: ['default' => 'FIFO'])]
    private SortiStockMode $sortiStockDefaut = SortiStockMode::FIFO;

    #[ORM\Column(options: ['default' => false])]
    private bool $gererStocks = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $autoriserStockNegatif = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getCodepostal(): ?string
    {
        return $this->codepostal;
    }

    public function setCodepostal(?string $codepostal): static
    {
        $this->codepostal = $codepostal;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): static
    {
        $this->pays = $pays;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getTheme(): ?Theme
    {
        return $this->theme instanceof Theme ? $this->theme : null;
    }

    public function setTheme(?Theme $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getRc(): ?string
    {
        return $this->rc;
    }

    public function setRc(?string $rc): static
    {
        $this->rc = $rc;

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): static
    {
        $this->siret = $siret;

        return $this;
    }

    public function getNaf(): ?string
    {
        return $this->naf;
    }

    public function setNaf(?string $naf): static
    {
        $this->naf = $naf;

        return $this;
    }

    public function getTvaintra(): ?string
    {
        return $this->tvaintra;
    }

    public function setTvaintra(?string $tvaintra): static
    {
        $this->tvaintra = $tvaintra;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getTel(): ?string
    {
        return $this->tel;
    }

    public function setTel(?string $tel): static
    {
        $this->tel = $tel;

        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): static
    {
        $this->iban = $iban;

        return $this;
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function setBic(?string $bic): static
    {
        $this->bic = $bic;

        return $this;
    }

    public function getDevise(): ?Devises
    {
        return $this->devise;
    }

    public function setDevise(?Devises $devise): static
    {
        $this->devise = $devise;

        return $this;
    }

    public function getFactureno(): ?int
    {
        return $this->factureno;
    }

    public function setFactureno(?int $factureno): static
    {
        $this->factureno = $factureno;

        return $this;
    }

    public function getDevisno(): ?int
    {
        return $this->devisno;
    }

    public function setDevisno(?int $devisno): static
    {
        $this->devisno = $devisno;

        return $this;
    }

    public function getCmdno(): ?int
    {
        return $this->cmdno;
    }

    public function setCmdno(?int $cmdno): static
    {
        $this->cmdno = $cmdno;

        return $this;
    }

    public function getBlno(): ?int
    {
        return $this->blno;
    }

    public function setBlno(?int $blno): static
    {
        $this->blno = $blno;

        return $this;
    }

    public function getPenalitesretard(): ?string
    {
        return $this->penalitesretard;
    }

    public function setPenalitesretard(?string $penalitesretard): static
    {
        $this->penalitesretard = $penalitesretard;

        return $this;
    }

    public function getNatureStock(): ?NatureProduction
    {
        return $this->natureStock;
    }

    public function setNatureStock(?NatureProduction $natureStock): static
    {
        $this->natureStock = $natureStock;

        return $this;
    }

    public function getSortiStockDefaut(): SortiStockMode
    {
        return $this->sortiStockDefaut;
    }

    public function setSortiStockDefaut(SortiStockMode $sortiStockDefaut): static
    {
        $this->sortiStockDefaut = $sortiStockDefaut;

        return $this;
    }

    public function isGererStocks(): bool
    {
        return $this->gererStocks;
    }

    public function setGererStocks(bool $gererStocks): static
    {
        $this->gererStocks = $gererStocks;

        return $this;
    }

    public function isAutoriserStockNegatif(): bool
    {
        return $this->autoriserStockNegatif;
    }

    public function setAutoriserStockNegatif(bool $autoriserStockNegatif): static
    {
        $this->autoriserStockNegatif = $autoriserStockNegatif;

        return $this;
    }

    public function __toString()
    {
        return $this->getNom();
    }
}

