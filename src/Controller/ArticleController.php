<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleFormType;
use App\Form\SearchArtFormType;
use App\Model\SearchDataArt;
use App\Repository\ArticleRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;



#[Route('article')]
class ArticleController extends AbstractController
{
    #[Route('/', name: 'article.list')]
    public function index(Request $request, ArticleRepository $artRepository ,ManagerRegistry $doctrine): Response
    {
        $page = $request->query->getInt('page', 1);
        $searchData = new SearchDataArt();
        $searchForm = $this->createForm(SearchArtFormType::class, $searchData);
        $searchForm->handleRequest($request);
        
        $searchActive = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchActive = $searchData;
        }

        $pagination = $artRepository->findPaginated($searchActive, $page);

        return $this->render('article/index.html.twig', [
            'search' => $searchForm->createView(),
            'articles' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
        ]);
    }
   

    #[Route('/{id<\d+>}', name: 'article.detail')]
    public function detail(ManagerRegistry $doctrine,$id): Response
    {
        $repository = $doctrine->getRepository(Article::class);
        $article = $repository->find($id);
        
       if(!$article){
            $this->addFlash(
            'error',
            "Le article n'existe pas"
            );
            return $this->redirectToRoute('article.list');
       }
        
        return $this->render('article/detail.html.twig', [
            'article' => $article
        ]);
    }
    
    #[Route('/edit/{id?0}', name: 'article.edit')]
    public function addArticle(ManagerRegistry $doctrine, Request $request, $id): Response
    {
       // $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repository = $doctrine->getRepository(Article::class);
        $article = $repository->find($id);
        $new = false;
        if(!$article){
            $article = new Article();
            $new = true;  
        }
        $article->doctrine=$doctrine;
        $article->user=$this->getUser();

       $form = $this->createForm(ArticleFormType::class, $article);
       $form->remove('dossier');
       $form->handleRequest($request);
       $newFilename = '';
       if($form->isSubmitted() && $form->isValid()){

        If ($new){
            $message = "Le article est ajouté avec succès";
            //$article->setCreatedBy($this->getUser());
            //$article->setCreatedAt(new \DateTimeImmutable('now'));
        }else{
            $message = "Le article a été mis à jour avec succès";
            //$article->setModifedBy($this->getUser());
            //$article->setModifedAt(new \DateTimeImmutable('now'));
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($article);
        $entityManager->flush();
        
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('article.edit', array('id' => $article->getId()));
        //return $this->redirectToRoute('article.list');
        
       }else{
            return $this->render('article/add-article.html.twig', [
                //'article' => $article,
                'form' => $form->createView(),
                /*'eleves' => $eleves,
                'cotisations' => $cotisations,*/
                'id' => $id
            ]);
       }
        
    }

    #[Route('/delete/{id}', name: 'article.delete')]
    public function deleteArticle(ManagerRegistry $doctrine, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repository = $doctrine->getRepository(Article::class);
        $article = $repository->find($id);
        if($article){
            $manager = $doctrine->getManager();
            $manager->remove($article);
            $manager->flush();
            $this->addFlash(
               'success',
               "L'article a été supprimé avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "L'article demandé n'existe pas"
             );
        }
        return $this->redirectToRoute('article.list');
    }
}
