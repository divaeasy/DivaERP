<?php

namespace App\Entity;

use App\Enum\SensEnum;
use App\Repository\CodeOperationRepository;
use App\Traits\TimeStampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CodeOperationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_CODE_OPERATION_LIBELLE', fields: ['libelle'])]
class CodeOperation
{
    use TimeStampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[ORM\Column(length: 20, enumType: SensEnum::class)]
    private SensEnum $sens = SensEnum::DEBIT;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $pieceTypeDevis = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $pieceTypeCommande = false;

    #[ORM\Column(name: 'piece_type_b_l', options: ['default' => false])]
    private bool $pieceTypeBL = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $pieceTypeFacture = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $pieceTypeInterne = false;

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

    public function getSens(): SensEnum
    {
        return $this->sens;
    }

    public function setSens(SensEnum $sens): static
    {
        $this->sens = $sens;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isPieceTypeDevis(): bool
    {
        return $this->pieceTypeDevis;
    }

    public function setPieceTypeDevis(bool $pieceTypeDevis): static
    {
        $this->pieceTypeDevis = $pieceTypeDevis;

        return $this;
    }

    public function isPieceTypeCommande(): bool
    {
        return $this->pieceTypeCommande;
    }

    public function setPieceTypeCommande(bool $pieceTypeCommande): static
    {
        $this->pieceTypeCommande = $pieceTypeCommande;

        return $this;
    }

    public function isPieceTypeBL(): bool
    {
        return $this->pieceTypeBL;
    }

    public function setPieceTypeBL(bool $pieceTypeBL): static
    {
        $this->pieceTypeBL = $pieceTypeBL;

        return $this;
    }

    public function isPieceTypeFacture(): bool
    {
        return $this->pieceTypeFacture;
    }

    public function setPieceTypeFacture(bool $pieceTypeFacture): static
    {
        $this->pieceTypeFacture = $pieceTypeFacture;

        return $this;
    }

    public function isPieceTypeInterne(): bool
    {
        return $this->pieceTypeInterne;
    }

    public function setPieceTypeInterne(bool $pieceTypeInterne): static
    {
        $this->pieceTypeInterne = $pieceTypeInterne;

        return $this;
    }

    public function __toString(): string
    {
        return (string) ($this->libelle ?? '');
    }
}
