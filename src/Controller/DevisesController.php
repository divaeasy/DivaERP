<?php

namespace App\Controller;

use App\Entity\Devises;
use App\Form\DeviseFormType;
use App\Form\SearchGenericFormType;
use App\Model\SearchGeneric;
use App\Repository\DevisesRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('devise')]
class DevisesController extends AbstractController
{
    #[Route('/', name: 'devise.list')]
    public function index(Request $request, DevisesRepository $devisesRepository, ManagerRegistry $doctrine): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $searchData = new SearchGeneric();
        $searchForm = $this->createForm(SearchGenericFormType::class, $searchData, ['placeholder' => 'Rechercher par code ou libellé...']);
        $searchForm->handleRequest($request);
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchData->page = $request->query->getInt('page', 1);
            $devises = $devisesRepository->findBySearch($searchData);
            return $this->render('devises/index.html.twig', [
                'search' => $searchForm->createView(),
                'devises' => $devises,
            ]);
        }

        $repository = $doctrine->getRepository(Devises::class);
        $devises = $repository->findAll();
         return $this->render('devises/index.html.twig', [
             'search' => $searchForm->createView(),
             'devises' => $devises,
         ]);
    }
    #[Route('/edit/{id?0}', name: 'devise.edit')]
    public function addDevise(ManagerRegistry $doctrine, Request $request, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Devises::class);
        $devise = $repository->find($id);
        $new = false;
        if(!$devise){
            $devise = new Devises();
            $new = true;
        }
        $devise->doctrine=$doctrine;
        $devise->user=$this->getUser();
        
       $form = $this->createForm(DeviseFormType::class, $devise);
       $form->handleRequest($request);
       $newFilename = '';
       if($form->isSubmitted() && $form->isValid()){

            
        If ($new){
            $message = "La devise est ajoutée avec succès";
            
        }else{
            $message = "La devise a été mise à jour avec succès";
           
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($devise);
        $entityManager->flush();
       
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('devise.list');
       }else{
            return $this->render('devises/add-devise.html.twig', [
                'devise'=>$form->createView(),
                'id' => $id
            ]);
       }
        
    }

    #[Route('/delete/{id}', name: 'devise.delete')]
    public function deleteDevise(ManagerRegistry $doctrine,$id): RedirectResponse
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Devises::class);
        $devise = $repository->find($id);
        if($devise){
            $manager = $doctrine->getManager();
            $manager->remove($devise);
            $manager->flush();
            $this->addFlash(
               'success',
               "La devise a été supprimée avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "La devise demandée n'existe pas"
             );
        }
        return $this->redirectToRoute('devise.list');
        
    }
}
