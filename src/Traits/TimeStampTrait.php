<?php

namespace App\Traits;

use App\Entity\Dossier;
use App\Entity\User;
use App\Service\DossierEncours;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\ManagerRegistry;

trait TimeStampTrait
{
    public ?ManagerRegistry $doctrine = null;
    public ?User $user = null;

    /**
     * Exclude non-serializable properties from serialization
     */
    public function __serialize(): array
    {
        $vars = get_object_vars($this);
        unset($vars['doctrine'], $vars['user']);
        return $vars;
    }

    /**
     * Restore properties after unserialization
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
        $this->doctrine = null;
        $this->user = null;
    }

    #[ORM\ManyToOne()]
    private ?User $createdBy = null;

    #[ORM\ManyToOne()]
    private ?User $modifedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

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
        $this->createdAt = new \DateTime();
        $this->createdBy = $this->user;

        // Only set dossier if the entity has that property and method
        if (method_exists($this, 'setDossier') && method_exists($this, 'getDossier') && $this->getDossier() === null) {
            if ($this->doctrine !== null && $this->user !== null) {
                $dossierEncours = new DossierEncours($this->doctrine); 
                $dossier = $dossierEncours->getDossier($this->user); 
                if ($dossier !== null) {
                    $this->setDossier($dossier);
                }
            }
        }
    }
    
    #[ORM\PreUpdate()]
    public function onPreUpdate(){
        $this->updatedAt = new \DateTime();
        $this->modifedBy = $this->user;
    }
}
