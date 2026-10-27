<?php

namespace App\Service;

use App\Entity\Dossier;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;

class DossierEncours 
{
    
    public function __construct(private ManagerRegistry $doctrine){
        
    }
    public function getDossier(User $user): ?Dossier
    {
        $current = $user->getCurrentDossier();
        if ($current !== null) {
            return $current;
        }

        $legacy = $user->getDossier();
        if ($legacy !== null) {
            return $legacy;
        }

        $first = $user->getDossiers()->first();
        return $first instanceof Dossier ? $first : null;
    }
    /*public function getUserEncours(){
       
        if (null !== $currentUser = $this->getUser()) {
            return $currentUser;
        }
        return 0;
    }*/
}
