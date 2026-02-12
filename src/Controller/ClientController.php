<?php

namespace App\Controller;

use App\Entity\Clients;
use App\Entity\User;
use App\Form\ClientFormType;
use App\Form\SearchFormType;
use App\Model\SearchData;
use App\Repository\ClientsRepository;
use App\Service\DossierEncours;
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

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $searchData = new SearchData();
        $searchForm = $this->createForm(SearchFormType::class, $searchData);
        $searchForm->handleRequest($request); 
        if ($searchForm->isSubmitted() && $searchForm->isValid()) { 
            $searchData->page = $request->query->getInt('page', 1);
            $clients = $cliRepository->findBySearch($searchData);
            return $this->render('client/index.html.twig', [
                'search' => $searchForm->createView(),
                'clients' => $clients
            ]);
        }


       $repository = $doctrine->getRepository(Clients::class);
       $clients = $repository->findBy([],['nom' => 'ASC']);
        return $this->render('client/index.html.twig', [
            'search' => $searchForm->createView(),
            'clients' => $clients,
        ]);
    }
   

    #[Route('/{id<\d+>}', name: 'client.detail')]
    public function detail(ManagerRegistry $doctrine,$id): Response
    {
        $repository = $doctrine->getRepository(Clients::class);
        $client = $repository->find($id);
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
        $client = $repository->find($id);
       
        
        $new = false;
        if(!$client){
            $client = new Clients(); 
            $new = true;  
        }
        $client->doctrine=$doctrine;
        $client->user=$this->getUser();
        
       
       $form = $this->createForm(ClientFormType::class, $client);
       $form->remove('dossier');
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

    
}

