<?php

namespace App\Controller;

use App\Entity\Depot;
use App\Entity\Dossier;
use App\Entity\User;
use App\Form\DepotFormType;
use App\Form\SearchGenericFormType;
use App\Model\SearchGeneric;
use App\Repository\DepotRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('depot')]
class DepotController extends AbstractController
{
    #[Route('/', name: 'app_depot_index')]
    public function index(Request $request, DepotRepository $depotRepository): Response
    {
        $page = $request->query->getInt('page', 1);
        $searchData = new SearchGeneric();
        $searchForm = $this->createForm(SearchGenericFormType::class, $searchData, ['placeholder' => 'Rechercher par libelle ou adresse...']);
        $searchForm->handleRequest($request);

        $searchActive = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchActive = $searchData;
        }

        $pagination = $depotRepository->findPaginated($searchActive, $page);

        return $this->render('depot/index.html.twig', [
            'search' => $searchForm->createView(),
            'depots' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
        ]);
    }

    #[Route('/{id<\d+>}', name: 'app_depot_show')]
    public function detail(ManagerRegistry $doctrine, int $id): Response
    {
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $depot = $doctrine->getRepository(Depot::class)->findOneBy([
            'id' => $id,
            'dossier' => $currentDossier,
        ]);

        if (!$depot instanceof Depot) {
            $this->addFlash('error', 'Le depot demande n existe pas.');

            return $this->redirectToRoute('app_depot_index');
        }

        return $this->render('depot/show.html.twig', [
            'depot' => $depot,
        ]);
    }

    #[Route('/new', name: 'app_depot_new')]
    #[Route('/edit/{id<\d+>}', name: 'app_depot_edit')]
    public function edit(ManagerRegistry $doctrine, Request $request, int $id = 0): Response
    {
        $repository = $doctrine->getRepository(Depot::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $depot = $repository->findOneBy([
            'id' => $id,
            'dossier' => $currentDossier,
        ]);

        $new = false;
        if (!$depot) {
            $depot = new Depot();
            $new = true;
            if ($currentDossier instanceof Dossier) {
                $depot->setDossier($currentDossier);
            }
        }

        $depot->setDoctrine($doctrine);
        $depot->setUser($this->getUser());

        $form = $this->createForm(DepotFormType::class, $depot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();
            $entityManager->persist($depot);
            $entityManager->flush();

            $this->addFlash('success', $new ? 'Le depot a ete ajoute avec succes.' : 'Le depot a ete mis a jour avec succes.');

            return $this->redirectToRoute('app_depot_index');
        }

        return $this->render($new ? 'depot/create.html.twig' : 'depot/edit.html.twig', [
            'form' => $form->createView(),
            'id' => $id,
            'depot' => $depot,
        ]);
    }

    #[Route('/delete/{id<\d+>}', name: 'app_depot_delete')]
    public function delete(ManagerRegistry $doctrine, int $id): Response
    {
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $depot = $doctrine->getRepository(Depot::class)->findOneBy([
            'id' => $id,
            'dossier' => $currentDossier,
        ]);

        if ($depot instanceof Depot) {
            $entityManager = $doctrine->getManager();
            $entityManager->remove($depot);
            $entityManager->flush();

            $this->addFlash('success', 'Le depot a ete supprime avec succes.');
        } else {
            $this->addFlash('error', 'Le depot demande n existe pas.');
        }

        return $this->redirectToRoute('app_depot_index');
    }
}
