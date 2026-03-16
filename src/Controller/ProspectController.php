<?php

namespace App\Controller;

use App\Entity\Clients;
use App\Entity\Prospects;
use App\Entity\User;
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
        $page = $request->query->getInt('page', 1);
        $searchData = new SearchData();
        $searchForm = $this->createForm(SearchFormType::class, $searchData);
        $searchForm->handleRequest($request); 
        
        $searchActive = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) { 
            $searchActive = $searchData;
        }

        $pagination = $cliRepository->findPaginated($searchActive, $page);

        return $this->render('prospect/index.html.twig', [
            'search' => $searchForm->createView(),
            'prospects' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
        ]);
    }
   

    #[Route('/{id<\d+>}', name: 'prospect.detail')]
    public function detail(ManagerRegistry $doctrine,$id): Response
    {
        $repository = $doctrine->getRepository(Prospects::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $prospect = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);
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
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $prospect = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);
        
        

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

    #[Route('/delete/{id}', name: 'prospect.delete')]
    public function deleteProspect(ManagerRegistry $doctrine, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repository = $doctrine->getRepository(Prospects::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $prospect = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);
        if($prospect){
            $manager = $doctrine->getManager();
            $manager->remove($prospect);
            $manager->flush();
            $this->addFlash(
               'success',
               "Le prospect a été supprimé avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "Le prospect demandé n'existe pas"
             );
        }
        return $this->redirectToRoute('prospect.list');
    }

    #[Route('/convert/{id}', name: 'prospect.convert')]
    public function convertToClient(ManagerRegistry $doctrine, int $id): Response
    {
        $repository = $doctrine->getRepository(Prospects::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $prospect = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);

        if (!$prospect) {
            $this->addFlash('error', "Le prospect n'existe pas");
            return $this->redirectToRoute('prospect.list');
        }

        // Create a new Client from the prospect data
        $client = new Clients();
        $client->setNom($prospect->getNom());
        $client->setAdr1($prospect->getAdr1());
        $client->setAdr2($prospect->getAdr2() ?? '');
        $client->setRue($prospect->getRue() ?? '');
        $client->setCodepostal($prospect->getCodepostal());
        $client->setVille($prospect->getVille());
        $client->setPays($prospect->getPays());
        $client->setTel($prospect->getTel() ?? '');
        $client->setEmail($prospect->getEmail() ?? '');
        $client->setWeb($prospect->getWeb() ?? '');
        $client->setLinkedin($prospect->getLinkedin() ?? '');
        $client->setDossier($prospect->getDossier());

        $entityManager = $doctrine->getManager();
        $entityManager->persist($client);
        
        // Remove the prospect
        $entityManager->remove($prospect);
        $entityManager->flush();

        $this->addFlash(
            'success',
            sprintf('Le prospect "%s" a été converti en client avec succès.', $prospect->getNom())
        );

        return $this->redirectToRoute('client.edit', ['id' => $client->getId()]);
    }
}
