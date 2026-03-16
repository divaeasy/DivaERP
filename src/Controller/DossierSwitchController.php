<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\DossierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class DossierSwitchController extends AbstractController
{
    #[Route('/switch-dossier/{id}', name: 'dossier.switch', methods: ['GET'])]
    public function switch(int $id, DossierRepository $dossierRepository, EntityManagerInterface $entityManager): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $dossier = $dossierRepository->find($id);
        if ($dossier === null || !$user->hasDossier($dossier)) {
            throw $this->createAccessDeniedException();
        }

        $user->setCurrentDossier($dossier);
        $entityManager->flush();

        return $this->redirectToRoute('app_dash_bord');
    }
}
