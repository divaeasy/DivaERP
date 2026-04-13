<?php

namespace App\Entity;

use App\Repository\EntetepieceRepository;
use App\Traits\TimeStampTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\ManagerRegistry;

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

    #[ORM\Column(length: 20)]
    private ?string $typet = null;

    #[ORM\Column(nullable: true)]
    private ?int $tierId = null;

    private ?string $resolvedTierName = null;

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

    #[ORM\Column(length: 20, nullable: true)]
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

    // E-Invoicing (Facture-X / Tiime) fields
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tiimeInvoiceId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tiimeSubmissionId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sellerSiren = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sellerSiret = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sellerVatNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $buyerSiren = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $buyerSiret = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $buyerVatNumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $invoiceType = null; // INVOICE, CREDIT_NOTE, DEBIT_NOTE, etc.

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $taxableAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $taxAmount = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $taxRate = null; // VAT rate (e.g., "20.00")

    #[ORM\Column(nullable: true)]
    private ?bool $isFactureX = false;

    #[ORM\Column(nullable: true)]
    private ?bool $isSubmittedToTiime = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $factureXPdfFilename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $factureXXmlFilename = null;

    #[ORM\OneToMany(targetEntity: InvoiceStatus::class, mappedBy: 'invoice', cascade: ['persist', 'remove'])]
    private Collection $invoiceStatuses;

    #[ORM\OneToMany(targetEntity: Lignepiece::class, mappedBy: 'piece', cascade: ['persist', 'remove'])]
    private Collection $lignepieces;

    public function __construct()
    {
        $this->invoiceStatuses = new ArrayCollection();
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

    public function getTierId(): ?int
    {
        return $this->tierId;
    }

    public function setTierId(?int $tierId): static
    {
        $this->tierId = $tierId;

        return $this;
    }

    public function getTier(?ManagerRegistry $doctrine = null): object|null
    {
        if ($this->tierId === null) {
            return null;
        }

        $registry = $doctrine ?? $this->doctrine;
        if (!$registry instanceof ManagerRegistry) {
            return null;
        }

        $tierClass = match ($this->normalizeTierToken($this->typet)) {
            'client' => Clients::class,
            'prospect' => Prospects::class,
            'fournisseur' => Fournisseur::class,
            default => null,
        };

        if ($tierClass === null) {
            return null;
        }

        return $registry->getRepository($tierClass)->find($this->tierId);
    }

    public function getTierName(?ManagerRegistry $doctrine = null): string
    {
        if ($this->resolvedTierName !== null && trim($this->resolvedTierName) !== '') {
            return $this->resolvedTierName;
        }

        $tier = $this->getTier($doctrine);
        if ($tier !== null && method_exists($tier, 'getNom')) {
            $name = trim((string) $tier->getNom());
            if ($name !== '') {
                return $name;
            }
        }

        if ($tier !== null && method_exists($tier, '__toString')) {
            $name = trim((string) $tier);
            if ($name !== '') {
                return $name;
            }
        }

        return 'N/A';
    }

    public function setResolvedTierName(?string $resolvedTierName): static
    {
        $this->resolvedTierName = $resolvedTierName;

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

    // E-Invoicing Getters & Setters

    public function getTiimeInvoiceId(): ?string
    {
        return $this->tiimeInvoiceId;
    }

    public function setTiimeInvoiceId(?string $tiimeInvoiceId): static
    {
        $this->tiimeInvoiceId = $tiimeInvoiceId;
        return $this;
    }

    public function getTiimeSubmissionId(): ?string
    {
        return $this->tiimeSubmissionId;
    }

    public function setTiimeSubmissionId(?string $tiimeSubmissionId): static
    {
        $this->tiimeSubmissionId = $tiimeSubmissionId;
        return $this;
    }

    public function getSellerSiren(): ?string
    {
        return $this->sellerSiren;
    }

    public function setSellerSiren(?string $sellerSiren): static
    {
        $this->sellerSiren = $sellerSiren;
        return $this;
    }

    public function getSellerSiret(): ?string
    {
        return $this->sellerSiret;
    }

    public function setSellerSiret(?string $sellerSiret): static
    {
        $this->sellerSiret = $sellerSiret;
        return $this;
    }

    public function getSellerVatNumber(): ?string
    {
        return $this->sellerVatNumber;
    }

    public function setSellerVatNumber(?string $sellerVatNumber): static
    {
        $this->sellerVatNumber = $sellerVatNumber;
        return $this;
    }

    public function getBuyerSiren(): ?string
    {
        return $this->buyerSiren;
    }

    public function setBuyerSiren(?string $buyerSiren): static
    {
        $this->buyerSiren = $buyerSiren;
        return $this;
    }

    public function getBuyerSiret(): ?string
    {
        return $this->buyerSiret;
    }

    public function setBuyerSiret(?string $buyerSiret): static
    {
        $this->buyerSiret = $buyerSiret;
        return $this;
    }

    public function getBuyerVatNumber(): ?string
    {
        return $this->buyerVatNumber;
    }

    public function setBuyerVatNumber(?string $buyerVatNumber): static
    {
        $this->buyerVatNumber = $buyerVatNumber;
        return $this;
    }

    public function getInvoiceType(): ?string
    {
        return $this->invoiceType;
    }

    public function setInvoiceType(?string $invoiceType): static
    {
        $this->invoiceType = $invoiceType;
        return $this;
    }

    public function getTaxableAmount(): ?string
    {
        return $this->taxableAmount;
    }

    public function setTaxableAmount(?string $taxableAmount): static
    {
        $this->taxableAmount = $taxableAmount;
        return $this;
    }

    public function getTaxAmount(): ?string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(?string $taxAmount): static
    {
        $this->taxAmount = $taxAmount;
        return $this;
    }

    public function getTaxRate(): ?string
    {
        return $this->taxRate;
    }

    public function setTaxRate(?string $taxRate): static
    {
        $this->taxRate = $taxRate;
        return $this;
    }

    public function isFactureX(): ?bool
    {
        return $this->isFactureX;
    }

    public function setFactureX(?bool $isFactureX): static
    {
        $this->isFactureX = $isFactureX;
        return $this;
    }

    public function isSubmittedToTiime(): ?bool
    {
        return $this->isSubmittedToTiime;
    }

    public function setSubmittedToTiime(?bool $isSubmittedToTiime): static
    {
        $this->isSubmittedToTiime = $isSubmittedToTiime;
        return $this;
    }

    public function getFactureXPdfFilename(): ?string
    {
        return $this->factureXPdfFilename;
    }

    public function setFactureXPdfFilename(?string $factureXPdfFilename): static
    {
        $this->factureXPdfFilename = $factureXPdfFilename;
        return $this;
    }

    public function getFactureXXmlFilename(): ?string
    {
        return $this->factureXXmlFilename;
    }

    public function setFactureXXmlFilename(?string $factureXXmlFilename): static
    {
        $this->factureXXmlFilename = $factureXXmlFilename;
        return $this;
    }

    /**
     * @return Collection<int, InvoiceStatus>
     */
    public function getInvoiceStatuses(): Collection
    {
        return $this->invoiceStatuses;
    }

    public function addInvoiceStatus(InvoiceStatus $invoiceStatus): static
    {
        if (!$this->invoiceStatuses->contains($invoiceStatus)) {
            $this->invoiceStatuses->add($invoiceStatus);
            $invoiceStatus->setInvoice($this);
        }
        return $this;
    }

    public function removeInvoiceStatus(InvoiceStatus $invoiceStatus): static
    {
        if ($this->invoiceStatuses->removeElement($invoiceStatus)) {
            if ($invoiceStatus->getInvoice() === $this) {
                $invoiceStatus->setInvoice(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Lignepiece>
     */
    public function getLignepieces(): Collection
    {
        return $this->lignepieces;
    }

    public function addLignepiece(Lignepiece $lignepiece): static
    {
        if (!$this->lignepieces->contains($lignepiece)) {
            $this->lignepieces->add($lignepiece);
            $lignepiece->setPiece($this);
        }
        return $this;
    }

    public function removeLignepiece(Lignepiece $lignepiece): static
    {
        if ($this->lignepieces->removeElement($lignepiece)) {
            if ($lignepiece->getPiece() === $this) {
                $lignepiece->setPiece(null);
            }
        }
        return $this;
    }

    private function normalizeTierToken(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value), 'UTF-8');
        $normalized = strtr($normalized, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ç' => 'c',
            'œ' => 'oe',
            'æ' => 'ae',
        ]);

        return (string) preg_replace('/[^a-z0-9]/', '', $normalized);
    }
}
