<?php

namespace App\Controller;

use App\Entity\Tarifvente;
use App\Form\TarifVenteFormType;
use App\Form\SearchGenericFormType;
use App\Model\SearchGeneric;
use App\Repository\TarifventeRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('tarifvente')]
class TarifsVenteController extends AbstractController
{
    #[Route('/', name: 'tarifvente.list')]
    public function index(Request $request, TarifventeRepository $tarifventeRepository, ManagerRegistry $doctrine): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $searchData = new SearchGeneric();
        $searchForm = $this->createForm(SearchGenericFormType::class, $searchData, ['placeholder' => 'Rechercher par prix...']);
        $searchForm->handleRequest($request);
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchData->page = $request->query->getInt('page', 1);
            $tarifventes = $tarifventeRepository->findBySearch($searchData);
            return $this->render('tarifvente/index.html.twig', [
                'search' => $searchForm->createView(),
                'tarifventes' => $tarifventes,
            ]);
        }

        $repository = $doctrine->getRepository(Tarifvente::class);
        $tarifventes = $repository->findAll();
         return $this->render('tarifvente/index.html.twig', [
             'search' => $searchForm->createView(),
             'tarifventes' => $tarifventes,
         ]);
    }
    #[Route('/edit/{id?0}', name: 'tarifvente.edit')]
    public function addTarif(ManagerRegistry $doctrine, Request $request, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(TarifVente::class);
        $tarifvente = $repository->find($id);
        $new = false;
        if(!$tarifvente){
            $tarifvente = new TarifVente();
            $new = true;
        }
        $tarifvente->doctrine=$doctrine;
        $tarifvente->user=$this->getUser();
        
       $form = $this->createForm(TarifVenteFormType::class, $tarifvente);
       $form->handleRequest($request);
       $newFilename = '';
       if($form->isSubmitted() && $form->isValid()){

            
        If ($new){
            $message = "Le tarif vente est ajouté avec succès";
            
        }else{
            $message = "Le tarif vente a été mis à jour avec succès";
           
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($tarifvente);
        $entityManager->flush();
       
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('tarifvente.list');
       }else{
            return $this->render('tarifvente/add-tarifvente.html.twig', [
                'tarifvente'=>$form->createView(),
                'id' => $id
            ]);
       }
        
    }

    #[Route('/delete/{id}', name: 'tarifvente.delete')]
    public function deleteTarif(ManagerRegistry $doctrine,$id): RedirectResponse
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(TarifVente::class);
        $tarifvente = $repository->find($id);
        if($tarifvente){
            $manager = $doctrine->getManager();
            $manager->remove($tarifvente);
            $manager->flush();
            $this->addFlash(
               'success',
               "Le tarif vente a été supprimé avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "Le tarif vente demandé n'existe pas"
             );
        }
        return $this->redirectToRoute('tarifvente.list');
        
    }
}
