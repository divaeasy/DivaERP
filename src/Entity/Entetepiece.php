<?php

namespace App\Entity;

use App\Repository\EntetepieceRepository;
use App\Traits\TimeStampTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntetepieceRepository::class)]
#[ORM\HasLifecycleCallbacks()]
class Entetepiece
{

    use TimeStampTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;


    #[ORM\Column(length: 8)]
    private ?string $type = null;

    #[ORM\Column(length: 8)]
    private ?string $typet = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Clients $client = null;

    #[ORM\Column]
    private ?int $pieceno = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pieceref = null;

    #[ORM\ManyToOne]
    private ?Devises $devise = null;

    #[ORM\Column(nullable: true)]
    private ?float $remise = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $delai = null;

    #[ORM\ManyToOne]
    private ?Reglement $reglement = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $edition = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rapport = null;

    #[ORM\Column(nullable: true)]
    private ?float $montant = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dossier $dossier = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $datep = null;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTypet(): ?string
    {
        return $this->typet;
    }

    public function setTypet(string $typet): static
    {
        $this->typet = $typet;

        return $this;
    }

    public function getClient(): ?Clients
    {
        return $this->client;
    }

    public function setClient(?Clients $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getPieceno(): ?int
    {
        return $this->pieceno;
    }

    public function setPieceno(int $pieceno): static
    {
        $this->pieceno = $pieceno;

        return $this;
    }

    public function getPieceref(): ?string
    {
        return $this->pieceref;
    }

    public function setPieceref(?string $pieceref): static
    {
        $this->pieceref = $pieceref;

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

    public function getRemise(): ?float
    {
        return $this->remise;
    }

    public function setRemise(?float $remise): static
    {
        $this->remise = $remise;

        return $this;
    }

    public function getDelai(): ?\DateTimeInterface
    {
        return $this->delai;
    }

    public function setDelai(?\DateTimeInterface $delai): static
    {
        $this->delai = $delai;

        return $this;
    }

    public function getReglement(): ?Reglement
    {
        return $this->reglement;
    }

    public function setReglement(?Reglement $reglement): static
    {
        $this->reglement = $reglement;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getEdition(): ?string
    {
        return $this->edition;
    }

    public function setEdition(?string $edition): static
    {
        $this->edition = $edition;

        return $this;
    }

    public function getRapport(): ?string
    {
        return $this->rapport;
    }

    public function setRapport(?string $rapport): static
    {
        $this->rapport = $rapport;

        return $this;
    }

    public function getMontant(): ?float
    {
        return $this->montant;
    }

    public function setMontant(?float $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getDatep(): ?\DateTimeInterface
    {
        return $this->datep;
    }

    public function setDatep(?\DateTimeInterface $datep): static
    {
        $this->datep = $datep;

        return $this;
    }
}
