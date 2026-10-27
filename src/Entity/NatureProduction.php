<?php

namespace App\Entity;

use App\Enum\NatureProductionType;
use App\Repository\NatureProductionRepository;
use App\Traits\TimeStampTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: NatureProductionRepository::class)]
#[ORM\HasLifecycleCallbacks()]
#[UniqueEntity(fields: ['libelle'], message: 'Cette nature de production existe deja.')]
class NatureProduction
{
    use TimeStampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $libelle = null;

    #[ORM\Column(length: 20, enumType: NatureProductionType::class)]
    private NatureProductionType $type = NatureProductionType::BIENS;

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

    public function getType(): NatureProductionType
    {
        return $this->type;
    }

    public function setType(NatureProductionType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->libelle;
    }
}
