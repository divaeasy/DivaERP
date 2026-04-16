<?php

namespace App\Entity;

use App\Repository\DepotRepository;
use App\Traits\TimeStampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DepotRepository::class)]
#[ORM\HasLifecycleCallbacks()]
class Depot
{
    use TimeStampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dossier $dossier = null;

    #[ORM\ManyToOne]
    private ?TiersInterne $tiersInterne = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adr1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adr2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rue = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $codepostal = null;

    #[ORM\ManyToOne]
    private ?Ville $ville = null;

    #[ORM\ManyToOne]
    private ?Pays $pays = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDossier(): ?Dossier
    {
        return $this->dossier;
    }

    public function setDossier(?Dossier $dossier): static
    {
        $this->dossier = $dossier;

        return $this;
    }

    public function getTiersInterne(): ?TiersInterne
    {
        return $this->tiersInterne;
    }

    public function setTiersInterne(?TiersInterne $tiersInterne): static
    {
        $this->tiersInterne = $tiersInterne;

        return $this;
    }

    public function getAdr1(): ?string
    {
        return $this->adr1;
    }

    public function setAdr1(?string $adr1): static
    {
        $this->adr1 = $adr1;

        return $this;
    }

    public function getAdr2(): ?string
    {
        return $this->adr2;
    }

    public function setAdr2(?string $adr2): static
    {
        $this->adr2 = $adr2;

        return $this;
    }

    public function getRue(): ?string
    {
        return $this->rue;
    }

    public function setRue(?string $rue): static
    {
        $this->rue = $rue;

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

    public function getVille(): ?Ville
    {
        return $this->ville;
    }

    public function setVille(?Ville $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getPays(): ?Pays
    {
        return $this->pays;
    }

    public function setPays(?Pays $pays): static
    {
        $this->pays = $pays;

        return $this;
    }

    public function getAdresse(): ?string
    {
        $address = [];
        foreach ([$this->adr1, $this->adr2, $this->rue] as $line) {
            if ($line) {
                $address[] = $line;
            }
        }

        $cityParts = [];
        if ($this->codepostal) {
            $cityParts[] = $this->codepostal;
        }
        if ($this->ville) {
            $cityParts[] = (string) $this->ville;
        }
        if ($cityParts !== []) {
            $address[] = implode(' ', $cityParts);
        }
        if ($this->pays) {
            $address[] = (string) $this->pays;
        }

        return implode(', ', $address) ?: null;
    }

    public function __toString(): string
    {
        return (string) $this->libelle;
    }
}
