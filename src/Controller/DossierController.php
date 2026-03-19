<?php

namespace App\Controller;

use App\Entity\Dossier;
use App\Form\DossierFormType;
use App\Form\SearchGenericFormType;
use App\Model\SearchGeneric;
use App\Repository\DossierRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('dossier')]
class DossierController extends AbstractController
{
    /**
     * Keep backward compatibility with legacy hex values stored before themed dossiers used keys.
     */
    private function normalizeThemeKey(?string $theme): string
    {
        $normalized = strtolower(trim((string) $theme));

        return match ($normalized) {
            'indigo', 'ocean', '#4e73df', '#224abe' => 'indigo',
            'ardoise', 'forest' => 'ardoise',
            'bleu-corporate', 'sand' => 'bleu-corporate',
            'emeraude', 'night' => 'emeraude',
            'minimal-clair', 'neutral', '', 'null' => 'minimal-clair',
            default => 'indigo',
        };
    }

    #[Route('/', name: 'dossier.list')]
    public function index(Request $request, DossierRepository $dossierRepository, ManagerRegistry $doctrine): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_ADMIN');

        $page = $request->query->getInt('page', 1);
        $searchData = new SearchGeneric();
        $searchForm = $this->createForm(SearchGenericFormType::class, $searchData, ['placeholder' => 'Rechercher par nom ou adresse...']);
        $searchForm->handleRequest($request);

        $searchActive = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchActive = $searchData;
        }

        $pagination = $dossierRepository->findPaginated($searchActive, $page);

        return $this->render('dossier/index.html.twig', [
            'search' => $searchForm->createView(),
            'dossiers' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
        ]);
    }
    #[Route('/edit/{id?0}', name: 'dossier.edit')]
    public function addDossier(ManagerRegistry $doctrine, Request $request, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Dossier::class);
        $dossier = $repository->find($id);
        $new = false;
        if(!$dossier){
            $dossier = new Dossier();
            $new = true;
        }
        $dossier->setTheme($this->normalizeThemeKey($dossier->getTheme()));
       // Dossier doesn't use TimeStampTrait, no need to set doctrine/user
        
       $form = $this->createForm(DossierFormType::class, $dossier);
       $form->handleRequest($request);
       if($form->isSubmitted() && $form->isValid()){

            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/dossiers';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $newFilename = uniqid('logo_', true) . '.' . $logoFile->guessExtension();
                $logoFile->move($uploadDir, $newFilename);
                $dossier->setLogo('/uploads/dossiers/' . $newFilename);
            }

            $dossier->setTheme($this->normalizeThemeKey($form->get('theme')->getData()));

            if ($new){
                $message = "Le dossier est ajouté avec succès";
            }else{
                $message = "Le dossier a été mis à jour avec succès";
            }
            $entityManager = $doctrine->getManager();
            $entityManager->persist($dossier);
            $entityManager->flush();

            $this->addFlash('success', $message);
            return $this->redirectToRoute('dossier.list');
       }else{
            return $this->render('dossier/add-dossier.html.twig', [
                'dossier'=>$form->createView(),
                'id' => $id
            ]);
       }
        
    }

    #[Route('/delete/{id}', name: 'dossier.delete')]
    public function deleteDossier(ManagerRegistry $doctrine,$id): RedirectResponse
    {
        //$this->denyAccessUnlessGranted('ROLE_ACMAR');
        $repository = $doctrine->getRepository(Dossier::class);
        $dossier = $repository->find($id);
        if($dossier){
            $manager = $doctrine->getManager();
            $manager->remove($dossier);
            $manager->flush();
            $this->addFlash(
               'success',
               "Le dossier a été supprimé avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "Le dossier demandé n'existe pas"
             );
        }
        return $this->redirectToRoute('dossier.list');
        
    }
}
