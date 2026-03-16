<?php

namespace App\Controller;

use App\Entity\Clients;
use App\Entity\User;
use App\Form\ClientFormType;
use App\Form\SearchFormType;
use App\Model\SearchData;
use App\Repository\ClientsRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('client')]
class ClientController extends AbstractController
{
    
    #[Route('/', name: 'client.list')]
    public function index(Request $request, ClientsRepository $cliRepository ,ManagerRegistry $doctrine): Response
    {
        $page = $request->query->getInt('page', 1);
        $searchData = new SearchData();
        $searchForm = $this->createForm(SearchFormType::class, $searchData);
        $searchForm->handleRequest($request); 
        
        $searchActive = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) { 
            $searchActive = $searchData;
        }

        $pagination = $cliRepository->findPaginated($searchActive, $page);

        return $this->render('client/index.html.twig', [
            'search' => $searchForm->createView(),
            'clients' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
        ]);
    }
   

    #[Route('/{id<\d+>}', name: 'client.detail')]
    public function detail(ManagerRegistry $doctrine,$id): Response
    {
        $repository = $doctrine->getRepository(Clients::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $client = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);
       if(!$client){
            $this->addFlash(
            'error',
            "Le client n'existe pas"
            );
            return $this->redirectToRoute('client.list');
       }
        
        return $this->render('client/detail.html.twig', [
            'client' => $client
        ]);
    }
    
    #[Route('/edit/{id?0}', name: 'client.edit')]
    public function addClient(ManagerRegistry $doctrine, Request $request, $id): Response
    {
       // $this->denyAccessUnlessGranted('ROLE_ADMIN');
       
        $repository = $doctrine->getRepository(Clients::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $client = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);
       
        
        $new = false;
        if(!$client){
            $client = new Clients(); 
            $new = true;  
        }
        $client->doctrine=$doctrine;
        $client->user=$this->getUser();
        
       
       $form = $this->createForm(ClientFormType::class, $client);
       $form->handleRequest($request);
       $newFilename = '';
       if($form->isSubmitted() && $form->isValid()){

        If ($new){
            $message = "Le client est ajouté avec succès";
            //$client->setCreatedBy($this->getUser());
            //$client->setCreatedAt(new \DateTimeImmutable('now'));
        }else{
            $message = "Le client a été mis à jour avec succès";
            //$client->setModifedBy($this->getUser());
            //$client->setModifedAt(new \DateTimeImmutable('now'));
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($client);
        $entityManager->flush();
        
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('client.edit', array('id' => $client->getId()));
        //return $this->redirectToRoute('client.list');
        
       }else{
            return $this->render('client/add-client.html.twig', [
                //'client' => $client,
                'form' => $form->createView(),
                /*'eleves' => $eleves,
                'cotisations' => $cotisations,*/
                'id' => $id
            ]);
       }
        
    }

    #[Route('/delete/{id}', name: 'client.delete')]
    public function deleteClient(ManagerRegistry $doctrine, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repository = $doctrine->getRepository(Clients::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $client = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);
        if($client){
            $manager = $doctrine->getManager();
            $manager->remove($client);
            $manager->flush();
            $this->addFlash(
               'success',
               "Le client a été supprimé avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "Le client demandé n'existe pas"
             );
        }
        return $this->redirectToRoute('client.list');
    }
}

