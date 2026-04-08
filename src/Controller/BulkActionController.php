<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BulkActionController extends AbstractController
{
    #[Route('/api/bulk-delete/articles', name: 'api.bulk_delete_articles', methods: ['POST'])]
    public function bulkDeleteArticles(Request $request, ManagerRegistry $doctrine): Response
    {
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if ($currentDossier === null) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun dossier courant sélectionné.',
            ], 403);
        }

        $payload = $request->request->all();
        if ($payload === []) {
            $rawContent = trim((string) $request->getContent());
            if ($rawContent !== '') {
                $decoded = json_decode($rawContent, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
        }

        if (!$this->isCsrfTokenValid('article_bulk_delete', (string) ($payload['_token'] ?? ''))) {
            return $this->json([
                'success' => false,
                'message' => 'Jeton de sécurité invalide.',
            ], 403);
        }

        $rawIds = $payload['ids'] ?? [];
        if (!is_array($rawIds)) {
            $rawIds = [$rawIds];
        }

        $idsByKey = [];
        foreach ($rawIds as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $idsByKey[$id] = $id;
            }
        }
        $ids = array_values($idsByKey);

        if ($ids === []) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun article sélectionné.',
            ], 422);
        }

        $repository = $doctrine->getRepository(Article::class);
        $articles = $repository->createQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->andWhere('a.dossier = :dossier')
            ->setParameter('ids', $ids)
            ->setParameter('dossier', $currentDossier)
            ->getQuery()
            ->getResult();

        if (!is_array($articles) || count($articles) === 0) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun article correspondant à votre sélection.',
            ], 404);
        }

        $manager = $doctrine->getManager();
        $deletedIds = [];

        foreach ($articles as $article) {
            if (!$article instanceof Article) {
                continue;
            }
            $deletedIds[] = $article->getId();
            $manager->remove($article);
        }

        if ($deletedIds === []) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun article supprimable trouvé.',
            ], 404);
        }

        $manager->flush();

        $deletedCount = count($deletedIds);
        $skippedCount = max(count($ids) - $deletedCount, 0);
        $message = $deletedCount > 1
            ? sprintf('%d articles supprimés avec succès.', $deletedCount)
            : '1 article supprimé avec succès.';

        if ($skippedCount > 0) {
            $message .= ' ' . sprintf('%d élément(s) ont été ignoré(s).', $skippedCount);
        }

        return $this->json([
            'success' => true,
            'message' => $message,
            'deletedIds' => $deletedIds,
            'deletedCount' => $deletedCount,
            'requestedCount' => count($ids),
            'skippedCount' => $skippedCount,
        ]);
    }
}
