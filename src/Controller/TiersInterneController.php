<?php

namespace App\Controller;

use App\Entity\Dossier;
use App\Entity\TiersInterne;
use App\Entity\User;
use App\Form\SearchFormType;
use App\Form\TiersInterneFormType;
use App\Model\SearchData;
use App\Repository\TiersInterneRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('tiers-interne')]
class TiersInterneController extends AbstractController
{
    #[Route('/', name: 'app_tiers_interne_index')]
    public function index(Request $request, TiersInterneRepository $tiersInterneRepository): Response
    {
        $page = $request->query->getInt('page', 1);
        $searchData = new SearchData();
        $searchForm = $this->createForm(SearchFormType::class, $searchData);
        $searchForm->handleRequest($request);

        $searchActive = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchActive = $searchData;
        }

        $pagination = $tiersInterneRepository->findPaginated($searchActive, $page);

        return $this->render('tiers_interne/index.html.twig', [
            'search' => $searchForm->createView(),
            'tiersInternes' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
        ]);
    }

    #[Route('/{id<\d+>}', name: 'app_tiers_interne_show')]
    public function detail(ManagerRegistry $doctrine, int $id): Response
    {
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $tiersInterne = $doctrine->getRepository(TiersInterne::class)->findOneBy([
            'id' => $id,
            'dossier' => $currentDossier,
        ]);

        if (!$tiersInterne instanceof TiersInterne) {
            $this->addFlash('error', 'Le tiers interne demande n existe pas.');

            return $this->redirectToRoute('app_tiers_interne_index');
        }

        return $this->render('tiers_interne/show.html.twig', [
            'tiersInterne' => $tiersInterne,
        ]);
    }

    #[Route('/new', name: 'app_tiers_interne_new')]
    #[Route('/edit/{id<\d+>}', name: 'app_tiers_interne_edit')]
    public function edit(ManagerRegistry $doctrine, Request $request, int $id = 0): Response
    {
        $repository = $doctrine->getRepository(TiersInterne::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $tiersInterne = $repository->findOneBy([
            'id' => $id,
            'dossier' => $currentDossier,
        ]);

        $new = false;
        if (!$tiersInterne) {
            $tiersInterne = new TiersInterne();
            $new = true;
            if ($currentDossier instanceof Dossier) {
                $tiersInterne->setDossier($currentDossier);
            }
        }

        $tiersInterne->setDoctrine($doctrine);
        $tiersInterne->setUser($this->getUser());

        $form = $this->createForm(TiersInterneFormType::class, $tiersInterne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();
            $entityManager->persist($tiersInterne);
            $entityManager->flush();

            $this->addFlash('success', $new ? 'Le tiers interne est ajoute avec succes.' : 'Le tiers interne a ete mis a jour avec succes.');

            return $this->redirectToRoute('app_tiers_interne_index');
        }

        return $this->render($new ? 'tiers_interne/create.html.twig' : 'tiers_interne/edit.html.twig', [
            'form' => $form->createView(),
            'id' => $id,
            'tiersInterne' => $tiersInterne,
        ]);
    }

    #[Route('/delete/{id<\d+>}', name: 'app_tiers_interne_delete')]
    public function delete(ManagerRegistry $doctrine, int $id): Response
    {
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $tiersInterne = $doctrine->getRepository(TiersInterne::class)->findOneBy([
            'id' => $id,
            'dossier' => $currentDossier,
        ]);

        if ($tiersInterne instanceof TiersInterne) {
            $entityManager = $doctrine->getManager();
            $entityManager->remove($tiersInterne);
            $entityManager->flush();

            $this->addFlash('success', 'Le tiers interne a ete supprime avec succes.');
        } else {
            $this->addFlash('error', 'Le tiers interne demande n existe pas.');
        }

        return $this->redirectToRoute('app_tiers_interne_index');
    }
}
