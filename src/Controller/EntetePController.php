<?php

namespace App\Controller;

use App\Entity\Dossier;
use App\Entity\Entetepiece;
use App\Entity\Lignepiece;
use App\Entity\User;
use App\Form\EntetePieceFormType;
use App\Repository\ClientsRepository;
use App\Repository\EntetepieceRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('piece')]
class EntetePController extends AbstractController
{

    public function __construct(private ManagerRegistry $doctrine2)
    {
         
    }

    #[Route('/', name: 'entetepiece.list')]
    public function index(ManagerRegistry $doctrine): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $repository = $doctrine->getRepository(EntetePiece::class);
        $entetepieces = $repository->findAll();
         return $this->render('entetepiece/index.html.twig', [
             'entetepieces' => $entetepieces,
         ]);
    }
    #[Route('/edit/{id?0}', name: 'entetepiece.edit')]
    public function addEntetePiece(ManagerRegistry $doctrine, Request $request, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(EntetePiece::class);
        $entetepiece = $repository->find($id);
        $repositoryUser = $doctrine->getRepository(User::class);
        $user = $repositoryUser->findBy(['id' => $this->getUser()]);
        $userId = $user[0]->getId();
        $repository2 = $doctrine->getRepository(Dossier::class);
        $dossier = $repository2->findBy(['id' => $user[0]->getDossier()]);

        $repositoryLignes = $doctrine->getRepository(Lignepiece::class);
        $lignepieces = $repositoryLignes->findBy(['piece' => $id]);

        $new = false;
        if(!$entetepiece){
            $entetepiece = new EntetePiece();
            $new = true;
        }
        $entetepiece->doctrine=$doctrine;
        $entetepiece->user=$this->getUser();
        
       $form = $this->createForm(EntetePieceFormType::class, $entetepiece);
       $form->remove('delai');
       $form->remove('edition');
       $form->remove('rapport');
       $form->remove('pieceno');
       $form->remove('dossier');

       $form->handleRequest($request);
       $newFilename = '';
       if($form->isSubmitted() && $form->isValid()){
        if (isset($dossier[0])){
            $NumFact = $dossier[0]->getFactureno() + 1;
            
        }else{
            $NumFact = 1;
           
        }
        
        If ($new){
            $message = "l'entête de pièce est ajouté avec succès";
            
            $entetepiece->setPieceno($NumFact);
            $entetepiece->setDossier($dossier[0]);
        }else{
            $message = "l'entête de pièce a été mis à jour avec succès";
           
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($entetepiece);
        $entityManager->flush();
       
        $this->addFlash(
           'success',
           $message
        );
        //return $this->redirectToRoute('entetepiece.list');
        return $this->redirectToRoute('entetepiece.edit', array('id' => $entetepiece->getId()));
       }else{
            return $this->render('entetepiece/add-entetepiece.html.twig', [
     
                'entetepiece'=>$form->createView(),
                'id'=>$id,
                'lignepieces'=>$lignepieces 
            ]);
       }
        
    }

    #[Route('/delete/{id}', name: 'entetepiece.delete')]
    public function deleteEntetePiece(ManagerRegistry $doctrine,$id): RedirectResponse
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Entetepiece::class);
        $entetepiece = $repository->find($id);
        if($entetepiece){
            $manager = $doctrine->getManager();
            $manager->remove($entetepiece);
            $manager->flush();
            $this->addFlash(
               'success',
               "l'entête de pièce a été supprimé avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "l'entête de pièce demandé n'existe pas"
             );
        }
        return $this->redirectToRoute('piece.list');
        
    }

    #[Route('/ca/annee/', name: 'ca_annee')]
    public function getCaAnneeMois(Request $request,EntetepieceRepository $repositoryPiece){
        //$doctrine2 = $this->getDoctrine();
        $annee = 2025;//$request->get('annee');
        $mois = 1;//$request->get('mois');
        
        If($annee>0)
        {
               
            $CaAnneeMois = $repositoryPiece->getCaAnneeMois($annee, $mois);
            
            $montant = $CaAnneeMois[0]["mont"];
             
            $response=new Response($montant);
            return $this->json(['code'=>200, 'message'=>$montant],200);
            
        }else
        {
            $response=new Response(0); 
        }
        return $this->json(['code'=>200, 'message'=>$response],200);//return $response;
        //return  $cotisation[0]->getMontCotisation();
    }
}
