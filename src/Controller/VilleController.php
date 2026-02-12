<?php

namespace App\Controller;

use App\Entity\Ville;
use App\Form\VilleFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('ville')]
class VilleController extends AbstractController
{
    #[Route('/', name: 'ville.list')]
    public function index(ManagerRegistry $doctrine): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $repository = $doctrine->getRepository(Ville::class);
        $villes = $repository->findAll();
         return $this->render('ville/index.html.twig', [
             'villes' => $villes,
         ]);
    }
    #[Route('/edit/{id?0}', name: 'ville.edit')]
    public function addVille(ManagerRegistry $doctrine, Request $request, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Ville::class);
        $ville = $repository->find($id);
        $new = false;
        if(!$ville){
            $ville = new Ville();
            $new = true;
        }
        $ville->doctrine=$doctrine;
        $ville->user=$this->getUser();
        
       $form = $this->createForm(VilleFormType::class, $ville);
       $form->handleRequest($request);
       $newFilename = '';
       if($form->isSubmitted() && $form->isValid()){

            
        If ($new){
            $message = "La ville est ajoutée avec succès";
            
        }else{
            $message = "La ville a été mise à jour avec succès";
           
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($ville);
        $entityManager->flush();
       
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('ville.list');
       }else{
            return $this->render('ville/add-ville.html.twig', [
     
                'ville'=>$form->createView()
            ]);
       }
        
    }

    #[Route('/delete/{id}', name: 'ville.delete')]
    public function deleteVille(ManagerRegistry $doctrine,$id): RedirectResponse
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Ville::class);
        $ville = $repository->find($id);
        if($ville){
            $manager = $doctrine->getManager();
            $manager->remove($ville);
            $manager->flush();
            $this->addFlash(
               'success',
               "La ville a été supprimée avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "La ville demandée n'existe pas"
             );
        }
        return $this->redirectToRoute('ville.list');
        
    }
}
