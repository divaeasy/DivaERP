<?php

namespace App\Entity;

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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\ManyToOne(targetEntity: Theme::class)]
    #[ORM\JoinColumn(name: 'theme_id', nullable: true, onDelete: 'SET NULL')]
    private Theme|string|null $theme = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rc = null;

    #[ORM\ManyToOne]
    private ?Devises $devise = null;

    #[ORM\Column(nullable: true)]
    private ?int $factureno = null;

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
    public function __toString()
    {
        return $this->getNom();
    }
}

