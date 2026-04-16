<?php

namespace App\Controller;

use App\Entity\NatureProduction;
use App\Form\NatureProductionFormType;
use App\Form\SearchGenericFormType;
use App\Model\SearchGeneric;
use App\Repository\NatureProductionRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('nature-production')]
class NatureProductionController extends AbstractController
{
    #[Route('/', name: 'app_nature_production_index')]
    public function index(Request $request, NatureProductionRepository $natureProductionRepository): Response
    {
        $page = $request->query->getInt('page', 1);
        $searchData = new SearchGeneric();
        $searchForm = $this->createForm(SearchGenericFormType::class, $searchData, ['placeholder' => 'Rechercher par libelle ou type...']);
        $searchForm->handleRequest($request);

        $searchActive = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchActive = $searchData;
        }

        $pagination = $natureProductionRepository->findPaginated($searchActive, $page);

        return $this->render('nature_production/index.html.twig', [
            'search' => $searchForm->createView(),
            'naturesProduction' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
        ]);
    }

    #[Route('/{id<\d+>}', name: 'app_nature_production_show')]
    public function detail(ManagerRegistry $doctrine, int $id): Response
    {
        $natureProduction = $doctrine->getRepository(NatureProduction::class)->find($id);

        if (!$natureProduction instanceof NatureProduction) {
            $this->addFlash('error', 'La nature de production demandee n existe pas.');

            return $this->redirectToRoute('app_nature_production_index');
        }

        return $this->render('nature_production/show.html.twig', [
            'natureProduction' => $natureProduction,
        ]);
    }

    #[Route('/new', name: 'app_nature_production_new')]
    #[Route('/edit/{id<\d+>}', name: 'app_nature_production_edit')]
    public function edit(ManagerRegistry $doctrine, Request $request, int $id = 0): Response
    {
        $repository = $doctrine->getRepository(NatureProduction::class);
        $natureProduction = $repository->find($id);
        $new = false;

        if (!$natureProduction) {
            $natureProduction = new NatureProduction();
            $new = true;
        }

        $natureProduction->setDoctrine($doctrine);
        $natureProduction->setUser($this->getUser());

        $form = $this->createForm(NatureProductionFormType::class, $natureProduction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();
            $entityManager->persist($natureProduction);
            $entityManager->flush();

            $this->addFlash('success', $new ? 'La nature de production a ete ajoutee avec succes.' : 'La nature de production a ete mise a jour avec succes.');

            return $this->redirectToRoute('app_nature_production_index');
        }

        return $this->render($new ? 'nature_production/create.html.twig' : 'nature_production/edit.html.twig', [
            'form' => $form->createView(),
            'id' => $id,
            'natureProduction' => $natureProduction,
        ]);
    }

    #[Route('/delete/{id<\d+>}', name: 'app_nature_production_delete')]
    public function delete(ManagerRegistry $doctrine, int $id): RedirectResponse
    {
        $natureProduction = $doctrine->getRepository(NatureProduction::class)->find($id);

        if ($natureProduction instanceof NatureProduction) {
            $entityManager = $doctrine->getManager();
            $entityManager->remove($natureProduction);
            $entityManager->flush();

            $this->addFlash('success', 'La nature de production a ete supprimee avec succes.');
        } else {
            $this->addFlash('error', 'La nature de production demandee n existe pas.');
        }

        return $this->redirectToRoute('app_nature_production_index');
    }
}
