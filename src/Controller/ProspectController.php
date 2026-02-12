<?php

namespace App\Controller;

use App\Entity\Prospects;
use App\Form\ProspectFormType;
use App\Form\SearchFormType;
use App\Model\SearchData;
use App\Repository\ProspectsRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('prospect')]
class ProspectController extends AbstractController
{
    #[Route('/', name: 'prospect.list')]
    public function index(Request $request, ProspectsRepository $cliRepository ,ManagerRegistry $doctrine): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $searchData = new SearchData();
        $searchForm = $this->createForm(SearchFormType::class, $searchData);
        $searchForm->handleRequest($request); 
        if ($searchForm->isSubmitted() && $searchForm->isValid()) { 
            $searchData->page = $request->query->getInt('page', 1);
            $prospects = $cliRepository->findBySearch($searchData);
            return $this->render('prospect/index.html.twig', [
                'search' => $searchForm->createView(),
                'prospects' => $prospects
            ]);
        }


       $repository = $doctrine->getRepository(Prospects::class);
       $prospects = $repository->findBy([],['nom' => 'ASC']);
        return $this->render('prospect/index.html.twig', [
            'search' => $searchForm->createView(),
            'prospects' => $prospects,
        ]);
    }
   

    #[Route('/{id<\d+>}', name: 'prospect.detail')]
    public function detail(ManagerRegistry $doctrine,$id): Response
    {
        $repository = $doctrine->getRepository(Prospects::class);
        $prospect = $repository->find($id);
       if(!$prospect){
            $this->addFlash(
            'error',
            "Le prospect n'existe pas"
            );
            return $this->redirectToRoute('prospect.list');
       }
        
        return $this->render('prospect/detail.html.twig', [
            'prospect' => $prospect
        ]);
    }
    
    #[Route('/edit/{id?0}', name: 'prospect.edit')]
    public function addProspect(ManagerRegistry $doctrine, Request $request, $id): Response
    {
       // $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repository = $doctrine->getRepository(Prospects::class);
        $prospect = $repository->find($id);
        
        

        $new = false;
        if(!$prospect){
            $prospect = new Prospects();
            $new = true;  
        }
        $prospect->doctrine=$doctrine;
        $prospect->user=$this->getUser();
       
       $form = $this->createForm(ProspectFormType::class, $prospect);
       $form->handleRequest($request);
       $newFilename = '';
       if($form->isSubmitted() && $form->isValid()){

        If ($new){
            $message = "Le prospect est ajouté avec succès";
            //$prospect->setCreatedBy($this->getUser());
            //$prospect->setCreatedAt(new \DateTimeImmutable('now'));
        }else{
            $message = "Le prospect a été mis à jour avec succès";
            //$prospect->setModifedBy($this->getUser());
            //$prospect->setModifedAt(new \DateTimeImmutable('now'));
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($prospect);
        $entityManager->flush();
        
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('prospect.edit', array('id' => $prospect->getId()));
        //return $this->redirectToRoute('prospect.list');
        
       }else{
            return $this->render('prospect/add-prospect.html.twig', [
                //'prospect' => $prospect,
                'form' => $form->createView(),
                /*'eleves' => $eleves,
                'cotisations' => $cotisations,*/
                'id' => $id
            ]);
       }
        
    }
}
