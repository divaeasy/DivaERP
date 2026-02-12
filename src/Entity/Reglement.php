<?php

namespace App\Entity;

use App\Repository\ReglementRepository;
use App\Traits\TimeStampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReglementRepository::class)]
#[ORM\HasLifecycleCallbacks()]
class Reglement
{
    use TimeStampTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[ORM\Column(nullable: true)]
    private ?int $echeance = null;


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

    public function getEcheance(): ?int
    {
        return $this->echeance;
    }

    public function setEcheance(?int $echeance): static
    {
        $this->echeance = $echeance;

        return $this;
    }
    public function __toString()
    {
        return $this->getLibelle();
    }
}
