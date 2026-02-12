<?php

namespace App\Service;

use App\Entity\Dossier;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DossierEncours 
{
    
    public function __construct(private ManagerRegistry $doctrine){
        
    }
    public function getDossier($user){
       
        $repository2 = $this->doctrine->getRepository(Dossier::class);
        $dossier = $repository2->findBy(['id' => $user->getDossier()]);
        return $dossier[0];
    }
    /*public function getUserEncours(){
       
        if (null !== $currentUser = $this->getUser()) {
            return $currentUser;
        }
        return 0;
    }*/
}