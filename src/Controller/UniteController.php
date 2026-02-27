<?php

namespace App\Controller;

use App\Entity\Unite;
use App\Form\UniteFormType;
use App\Form\SearchGenericFormType;
use App\Model\SearchGeneric;
use App\Repository\UniteRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('unite')]
class UniteController extends AbstractController
{
    #[Route('/', name: 'unite.list')]
    public function index(Request $request, UniteRepository $uniteRepository, ManagerRegistry $doctrine): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $searchData = new SearchGeneric();
        $searchForm = $this->createForm(SearchGenericFormType::class, $searchData, ['placeholder' => 'Rechercher par code ou libellé...']);
        $searchForm->handleRequest($request);
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchData->page = $request->query->getInt('page', 1);
            $unites = $uniteRepository->findBySearch($searchData);
            return $this->render('unite/index.html.twig', [
                'search' => $searchForm->createView(),
                'unites' => $unites,
            ]);
        }

        $repository = $doctrine->getRepository(Unite::class);
        $unites = $repository->findAll();
         return $this->render('unite/index.html.twig', [
             'search' => $searchForm->createView(),
             'unites' => $unites,
         ]);
    }
    #[Route('/edit/{id?0}', name: 'unite.edit')]
    public function addUnite(ManagerRegistry $doctrine, Request $request, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Unite::class);
        $unite = $repository->find($id);
        $new = false;
        if(!$unite){
            $unite = new Unite();
            $new = true;
        }
        $unite->doctrine=$doctrine;
        $unite->user=$this->getUser();
        
       $form = $this->createForm(UniteFormType::class, $unite);
       $form->handleRequest($request);
       $newFilename = '';
       if($form->isSubmitted() && $form->isValid()){

            
        If ($new){
            $message = "l'unité est ajoutée avec succès";
            
        }else{
            $message = "l'unité a été mise à jour avec succès";
           
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($unite);
        $entityManager->flush();
       
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('unite.list');
       }else{
            return $this->render('unite/add-unite.html.twig', [
                'unite'=>$form->createView(),
                'id' => $id
            ]);
       }
        
    }

    #[Route('/delete/{id}', name: 'unite.delete')]
    public function deleteUnite(ManagerRegistry $doctrine,$id): RedirectResponse
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Unite::class);
        $unite = $repository->find($id);
        if($unite){
            $manager = $doctrine->getManager();
            $manager->remove($unite);
            $manager->flush();
            $this->addFlash(
               'success',
               "l'unité a été supprimée avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "l'unité demandée n'existe pas"
             );
        }
        return $this->redirectToRoute('unite.list');
        
    }
}
