<?php

namespace App\Controller;

use App\Entity\Fournisseur;
use App\Entity\User;
use App\Form\FournisseurFormType;
use App\Form\SearchFormType;
use App\Model\SearchData;
use App\Repository\FournisseurRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('fournisseur')]
class FournisseurController extends AbstractController
{
    #[Route('/', name: 'fournisseur.list')]
    public function index(Request $request, FournisseurRepository $fournisseurRepository): Response
    {
        $page = $request->query->getInt('page', 1);
        $searchData = new SearchData();
        $searchForm = $this->createForm(SearchFormType::class, $searchData);
        $searchForm->handleRequest($request);

        $searchActive = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchActive = $searchData;
        }

        $pagination = $fournisseurRepository->findPaginated($searchActive, $page);

        return $this->render('fournisseur/index.html.twig', [
            'search' => $searchForm->createView(),
            'fournisseurs' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
        ]);
    }

    #[Route('/{id<\d+>}', name: 'fournisseur.detail')]
    public function detail(ManagerRegistry $doctrine, int $id): Response
    {
        $repository = $doctrine->getRepository(Fournisseur::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $fournisseur = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);

        if (!$fournisseur) {
            $this->addFlash('error', "Le fournisseur n'existe pas");

            return $this->redirectToRoute('fournisseur.list');
        }

        return $this->render('fournisseur/detail.html.twig', [
            'fournisseur' => $fournisseur,
        ]);
    }

    #[Route('/edit/{id?0}', name: 'fournisseur.edit')]
    public function addFournisseur(ManagerRegistry $doctrine, Request $request, int $id): Response
    {
        $repository = $doctrine->getRepository(Fournisseur::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $fournisseur = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);

        $new = false;
        if (!$fournisseur) {
            $fournisseur = new Fournisseur();
            $new = true;
        }

        $fournisseur->doctrine = $doctrine;
        $fournisseur->user = $this->getUser();

        $form = $this->createForm(FournisseurFormType::class, $fournisseur);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($new) {
                $message = 'Le fournisseur est ajouté avec succès';
            } else {
                $message = 'Le fournisseur a été mis à jour avec succès';
            }

            $entityManager = $doctrine->getManager();
            $entityManager->persist($fournisseur);
            $entityManager->flush();

            $this->addFlash('success', $message);

            return $this->redirectToRoute('fournisseur.list');
        }

        return $this->render('fournisseur/add-fournisseur.html.twig', [
            'form' => $form->createView(),
            'id' => $id,
        ]);
    }

    #[Route('/delete/{id}', name: 'fournisseur.delete')]
    public function deleteFournisseur(ManagerRegistry $doctrine, int $id): Response
    {
        $repository = $doctrine->getRepository(Fournisseur::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $fournisseur = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);

        if ($fournisseur) {
            $manager = $doctrine->getManager();
            $manager->remove($fournisseur);
            $manager->flush();

            $this->addFlash('success', 'Le fournisseur a été supprimé avec succès');
        } else {
            $this->addFlash('error', "Le fournisseur demandé n'existe pas");
        }

        return $this->redirectToRoute('fournisseur.list');
    }
}
