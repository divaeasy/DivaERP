<?php

namespace App\Controller;

use App\Entity\Reglement;
use App\Form\ReglementFormType;
use App\Form\SearchGenericFormType;
use App\Model\SearchGeneric;
use App\Repository\ReglementRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('reglement')]
class ReglementController extends AbstractController
{
    #[Route('/', name: 'reglement.list')]
    public function index(Request $request, ReglementRepository $reglementRepository, ManagerRegistry $doctrine): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $page = $request->query->getInt('page', 1);
        $searchData = new SearchGeneric();
        $searchForm = $this->createForm(SearchGenericFormType::class, $searchData, ['placeholder' => 'Rechercher par libellé...']);
        $searchForm->handleRequest($request);

        $searchActive = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchActive = $searchData;
        }

        $pagination = $reglementRepository->findPaginated($searchActive, $page);

        return $this->render('reglement/index.html.twig', [
            'search' => $searchForm->createView(),
            'reglements' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
        ]);
    }
    #[Route('/edit/{id?0}', name: 'reglement.edit')]
    public function addReglement(ManagerRegistry $doctrine, Request $request, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Reglement::class);
        $reglement = $repository->find($id);
        $new = false;
        if(!$reglement){
            $reglement = new Reglement();
            $new = true;
        }
        $reglement->doctrine=$doctrine;
        $reglement->user=$this->getUser();
        
       $form = $this->createForm(ReglementFormType::class, $reglement);
       $form->handleRequest($request);
       $newFilename = '';
       if($form->isSubmitted() && $form->isValid()){

            
        If ($new){
            $message = "Le réglement est ajouté avec succès";
            
        }else{
            $message = "Le réglement a été mis à jour avec succès";
           
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($reglement);
        $entityManager->flush();
       
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('reglement.list');
       }else{
            return $this->render('reglement/add-reglement.html.twig', [
                'reglement'=>$form->createView(),
                'id' => $id
            ]);
       }
        
    }

    #[Route('/delete/{id}', name: 'reglement.delete')]
    public function deleteReglement(ManagerRegistry $doctrine,$id): RedirectResponse
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Reglement::class);
        $reglement = $repository->find($id);
        if($reglement){
            $manager = $doctrine->getManager();
            $manager->remove($reglement);
            $manager->flush();
            $this->addFlash(
               'success',
               "Le réglement a été supprimé avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "Le réglement demandé n'existe pas"
             );
        }
        return $this->redirectToRoute('reglement.list');
        
    }
}
