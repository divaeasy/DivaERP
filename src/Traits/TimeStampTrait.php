<?php

namespace App\Traits;

use App\Entity\Dossier;
use App\Entity\User;
use App\Repository\TimeStampTraitRepository;
use App\Service\DossierEncours;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\ManagerRegistry;

#[ORM\Entity(repositoryClass: TimeStampTraitRepository::class)]
trait TimeStampTrait
{
    
    public function __construct(ManagerRegistry $doctrine=null, User $user=null)
    {
              
    }
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    
    #[ORM\ManyToOne()]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(inversedBy: 'createdAt')]
    private ?User $modifedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\ManyToOne]
    //#[ORM\JoinColumn(nullable: false)]
    private ?Dossier $dossier = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

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

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }
    public function getModifedBy(): ?User
    {
        return $this->modifedBy;
    }

    public function setModifedBy(?User $modifedBy): self
    {
        $this->modifedBy = $modifedBy;

        return $this;
    }
  
    #[ORM\PrePersist()]
    public function onPrePersist(){
        //dd(new \DateTime());
        $this->createdAt = new \DateTime();
        //$this->updatedAt = new \DateTime();
        $this->createdBy = $this->user;
       // $this->modifedBy = $this->user;

        $dossierEncours = new DossierEncours($this->doctrine); 
        $dossier = $dossierEncours->getDossier($this->user); 
        $this->dossier = ($dossier);

    }
    
    #[ORM\PreUpdate()]
    public function onPreUpdate(){
        
        $this->updatedAt = new \DateTime();
        $this->modifedBy = $this->user;
        //dd($this->user);
        
    }
}
