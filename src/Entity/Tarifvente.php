<?php

namespace App\Entity;

use App\Repository\TarifventeRepository;
use App\Traits\TimeStampTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TarifventeRepository::class)]
#[ORM\HasLifecycleCallbacks()]
class Tarifvente
{
    use TimeStampTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    private ?Tarifs $tarif = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Article $article = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Clients $client = null;

    #[ORM\ManyToOne]
    private ?Devises $devise = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateeffet = null;

    #[ORM\Column(nullable: true)]
    private ?float $prix = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dossier $dossier = null;

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

    public function getTarif(): ?Tarifs
    {
        return $this->tarif;
    }

    public function setTarif(?Tarifs $tarif): static
    {
        $this->tarif = $tarif;

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

    public function getClient(): ?Clients
    {
        return $this->client;
    }

    public function setClient(?Clients $client): static
    {
        $this->client = $client;

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

    public function getDateeffet(): ?\DateTimeInterface
    {
        return $this->dateeffet;
    }

    public function setDateeffet(?\DateTimeInterface $dateeffet): static
    {
        $this->dateeffet = $dateeffet;

        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(?float $prix): static
    {
        $this->prix = $prix;

        return $this;
    }
}
