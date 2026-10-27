<?php

namespace App\Controller;

use App\Entity\Pays;
use App\Form\PaysFormType;
use App\Form\SearchGenericFormType;
use App\Model\SearchGeneric;
use App\Repository\PaysRepository;
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
    public function index(Request $request, PaysRepository $paysRepository, ManagerRegistry $doctrine): Response
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

        $pagination = $paysRepository->findPaginated($searchActive, $page);

        return $this->render('pays/index.html.twig', [
            'search' => $searchForm->createView(),
            'pays' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
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
                'pays'=>$form->createView(),
                'id' => $id
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
