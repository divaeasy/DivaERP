<?php

namespace App\Controller;

use App\Entity\Entetepiece;
use App\Entity\Lignepiece;
use App\Entity\User;
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
    private function computeMontant(Lignepiece $lignepiece): float
    {
        $qte = (float) ($lignepiece->getQte() ?? 0.0);
        $pub = (float) ($lignepiece->getPub() ?? 0.0);
        $remise = (float) ($lignepiece->getRemise() ?? 0.0);

        $montant = $qte * $pub * (1 - $remise / 100);
        if (!is_finite($montant)) {
            $montant = 0.0;
        }

        return round($montant, 2);
    }
    #[Route('/', name: 'lignepiece.list')]
    public function index(ManagerRegistry $doctrine): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $repository = $doctrine->getRepository(Lignepiece::class);
        $user = $this->getUser();
        if ($user instanceof User && $user->getCurrentDossier() !== null) {
            $lignepieces = $repository->findBy(['dossier' => $user->getCurrentDossier()]);
        } else {
            $lignepieces = [];
        }
         return $this->render('lignepiece/index.html.twig', [
             'lignepieces' => $lignepieces,
         ]);
    }
    #[Route('/edit/{id?0}/{pceId?0}', name: 'lignepiece.edit')]
    public function UpdateLignepiece(ManagerRegistry $doctrine, Request $request, $id,$pceId): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Lignepiece::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $lignepiece = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);
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
        if ($lignepiece->getDossier() === null && $lignepiece->getPiece() !== null) {
            $lignepiece->setDossier($lignepiece->getPiece()->getDossier());
        }
        $lignepiece->setMontant($this->computeMontant($lignepiece));
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
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $entetePiece = $repository->findOneBy(['id' => $pceId, 'dossier' => $currentDossier]);
        if ($entetePiece === null) {
            $this->addFlash('error', "La pièce demandée n'existe pas");
            return $this->redirectToRoute('entetepiece.list');
        }
        
        $new = false;
        $lignepiece = new Lignepiece();
        
        $lignepiece->doctrine=$doctrine; 
        $lignepiece->user=$this->getUser();
        $lignepiece->setPiece($entetePiece);
        if ($entetePiece !== null) {
            $lignepiece->setDossier($entetePiece->getDossier());
        }
       $form = $this->createForm(LignepieceFormType::class, $lignepiece);
       $form->handleRequest($request);
       
       if($form->isSubmitted() && $form->isValid()){

        $message = "La lignepiece est ajoutée avec succès";
       
        $lignepiece->setMontant($this->computeMontant($lignepiece));
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
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $lignepiece = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);
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
