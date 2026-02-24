<?php

namespace App\Controller;

use App\Entity\Entetepiece;
use App\Entity\Lignepiece;
use App\Form\LignepieceFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('lignepiece')]
class LignePController extends AbstractController
{
    #[Route('/', name: 'lignepiece.list')]
    public function index(ManagerRegistry $doctrine): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $repository = $doctrine->getRepository(Lignepiece::class);
        $lignepieces = $repository->findAll();
         return $this->render('lignepiece/index.html.twig', [
             'lignepieces' => $lignepieces,
         ]);
    }
    #[Route('/edit/{id?0}/{pceId?0}', name: 'lignepiece.edit')]
    public function UpdateLignepiece(ManagerRegistry $doctrine, Request $request, $id,$pceId): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Lignepiece::class);
        $lignepiece = $repository->find($id);
        $new = false;
        if(!$lignepiece){
            $lignepiece = new Lignepiece();
            $new = true;
        }
        $lignepiece->doctrine=$doctrine;
        $lignepiece->user=$this->getUser();
        
       $form = $this->createForm(LignepieceFormType::class, $lignepiece);
       $form->handleRequest($request);
       $newFilename = '';
       if($form->isSubmitted() && $form->isValid()){

            
        If ($new){
            $message = "La lignepiece est ajoutée avec succès";
            
        }else{
            $message = "La lignepiece a été mise à jour avec succès";
           
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($lignepiece);
        $entityManager->flush();
       
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('entetepiece.edit', array('id' => $pceId));
       }else{
            return $this->render('lignepiece/add-lignepiece.html.twig', [
                'lignepiece'=>$form->createView(),
                'id' => $id,
                'pceId' => $pceId
            ]);
       }
        
    }
    #[Route('/add/{pceId?0}', name: 'lignepiece.add')]
    public function addLignepiece(ManagerRegistry $doctrine, Request $request, $pceId): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Entetepiece::class);
        $entetePiece = $repository->find($pceId);
        
        $new = false;
        $lignepiece = new Lignepiece();
        
        $lignepiece->doctrine=$doctrine; 
        $lignepiece->user=$this->getUser();
        $lignepiece->setPiece($entetePiece) ;
       $form = $this->createForm(LignepieceFormType::class, $lignepiece);
       $form->handleRequest($request);
       
       if($form->isSubmitted() && $form->isValid()){

        $message = "La lignepiece est ajoutée avec succès";
       
        $entityManager = $doctrine->getManager();
        $entityManager->persist($lignepiece);
        $entityManager->flush();
       
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('entetepiece.edit', array('id' => $pceId));
       }else{
            return $this->render('lignepiece/add-lignepiece.html.twig', [
                'lignepiece'=>$form->createView(),
                'id' => 0,
                'pceId' => $pceId
            ]);
       }
        
    }



    #[Route('/delete/{id}', name: 'lignepiece.delete')]
    public function deleteLignepiece(ManagerRegistry $doctrine,$id): RedirectResponse
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Lignepiece::class);
        $lignepiece = $repository->find($id);
        if($lignepiece){
            $manager = $doctrine->getManager();
            $manager->remove($lignepiece);
            $manager->flush();
            $this->addFlash(
               'success',
               "La lignepiece a été supprimée avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "La lignepiece demandée n'existe pas"
             );
        }
        return $this->redirectToRoute('lignepiece.list');
        
    }
}
