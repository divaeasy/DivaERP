<?php

namespace App\Controller;

use App\Entity\Dossier;
use App\Form\DossierFormType;
use App\Repository\DossierRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('dossier')]
class DossierController extends AbstractController
{
    #[Route('/', name: 'dossier.list')]
    public function index(ManagerRegistry $doctrine): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $repository = $doctrine->getRepository(Dossier::class);
        $dossiers = $repository->findAll();
         return $this->render('dossier/index.html.twig', [
             'dossiers' => $dossiers,
         ]);
    }
    #[Route('/edit/{id?0}', name: 'dossier.edit')]
    public function addDossier(ManagerRegistry $doctrine, Request $request, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Dossier::class);
        $dossier = $repository->find($id);
        $new = false;
        if(!$dossier){
            $dossier = new Dossier();
            $new = true;
        }
        // Dossier doesn't use TimeStampTrait, no need to set doctrine/user
        
       $form = $this->createForm(DossierFormType::class, $dossier);
       $form->handleRequest($request);
       $newFilename = '';
       if($form->isSubmitted() && $form->isValid()){

            
        If ($new){
            $message = "Le dossier est ajouté avec succès";
            
        }else{
            $message = "Le dossier a été mis à jour avec succès";
           
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($dossier);
        $entityManager->flush();
       
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('dossier.list');
       }else{
            return $this->render('dossier/add-dossier.html.twig', [
                'dossier'=>$form->createView(),
                'id' => $id
            ]);
       }
        
    }

    #[Route('/delete/{id}', name: 'dossier.delete')]
    public function deleteDossier(ManagerRegistry $doctrine,$id): RedirectResponse
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Dossier::class);
        $dossier = $repository->find($id);
        if($dossier){
            $manager = $doctrine->getManager();
            $manager->remove($dossier);
            $manager->flush();
            $this->addFlash(
               'success',
               "Le dossier a été supprimé avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "Le dossier demandé n'existe pas"
             );
        }
        return $this->redirectToRoute('dossier.list');
        
    }
}
