<?php

namespace App\Controller;

use App\Entity\Pays;
use App\Form\PaysFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('pays')]
class PaysController extends AbstractController
{
    #[Route('/', name: 'pays.list')]
    public function index(ManagerRegistry $doctrine): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $repository = $doctrine->getRepository(Pays::class);
        $pays = $repository->findAll();
         return $this->render('pays/index.html.twig', [
             'pays' => $pays,
         ]);
    }
    #[Route('/edit/{id?0}', name: 'pays.edit')]
    public function addPays(ManagerRegistry $doctrine, Request $request, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Pays::class);
        $pays = $repository->find($id);
        $new = false;
        if(!$pays){
            $pays = new Pays();
            $new = true;
        }
        $pays->doctrine=$doctrine;
        $pays->user=$this->getUser();
        
       $form = $this->createForm(PaysFormType::class, $pays);
       $form->handleRequest($request);
       $newFilename = '';
       if($form->isSubmitted() && $form->isValid()){

            
        If ($new){
            $message = "Le pays est ajouté avec succès";
            
        }else{
            $message = "Le pays a été mis à jour avec succès";
           
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($pays);
        $entityManager->flush();
       
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('pays.list');
       }else{
            return $this->render('pays/add-pays.html.twig', [
     
                'pays'=>$form->createView()
            ]);
       }
        
    }

    #[Route('/delete/{id}', name: 'pays.delete')]
    public function deletePays(ManagerRegistry $doctrine,$id): RedirectResponse
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Pays::class);
        $pays = $repository->find($id);
        if($pays){
            $manager = $doctrine->getManager();
            $manager->remove($pays);
            $manager->flush();
            $this->addFlash(
               'success',
               "Le pays a été supprimé avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "Le pays demandé n'existe pas"
             );
        }
        return $this->redirectToRoute('pays.list');
        
    }
}
