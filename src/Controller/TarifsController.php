<?php

namespace App\Controller;

use App\Entity\Tarifs;
use App\Form\TarifsFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('tarif')]
class TarifsController extends AbstractController
{
    #[Route('/', name: 'tarif.list')]
    public function index(ManagerRegistry $doctrine): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $repository = $doctrine->getRepository(Tarifs::class);
        $tarifs = $repository->findAll();
         return $this->render('tarifs/index.html.twig', [
             'tarifs' => $tarifs,
         ]);
    }
    #[Route('/edit/{id?0}', name: 'tarif.edit')]
    public function addTarif(ManagerRegistry $doctrine, Request $request, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Tarifs::class);
        $tarif = $repository->find($id);
        $new = false;
        if(!$tarif){
            $tarif = new Tarifs();
            $new = true;
        }
        $tarif->doctrine=$doctrine;
        $tarif->user=$this->getUser();
        
       $form = $this->createForm(TarifsFormType::class, $tarif);
       $form->handleRequest($request);
       $newFilename = '';
       if($form->isSubmitted() && $form->isValid()){

            
        If ($new){
            $message = "Le tarif est ajouté avec succès";
            
        }else{
            $message = "Le tarif a été mis à jour avec succès";
           
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($tarif);
        $entityManager->flush();
       
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('tarif.list');
       }else{
            return $this->render('tarifs/add-tarif.html.twig', [
                'tarif'=>$form->createView(),
                'id' => $id
            ]);
       }
        
    }

    #[Route('/delete/{id}', name: 'tarif.delete')]
    public function deleteTarif(ManagerRegistry $doctrine,$id): RedirectResponse
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Tarifs::class);
        $tarif = $repository->find($id);
        if($tarif){
            $manager = $doctrine->getManager();
            $manager->remove($tarif);
            $manager->flush();
            $this->addFlash(
               'success',
               "Le tarif a été supprimé avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "Le tarif demandé n'existe pas"
             );
        }
        return $this->redirectToRoute('tarif.list');
        
    }
}
