<?php

namespace App\Controller;

use App\Entity\Clients;
use App\Entity\Article;
use App\Entity\CodeOperation;
use App\Entity\Depot;
use App\Entity\Devises;
use App\Entity\Dossier;
use App\Entity\Entetepiece;
use App\Entity\Fournisseur;
use App\Entity\NatureProduction;
use App\Entity\Pays;
use App\Entity\Prospects;
use App\Entity\Reglement;
use App\Entity\Tarifs;
use App\Entity\Tarifvente;
use App\Entity\TiersInterne;
use App\Entity\Unite;
use App\Entity\User;
use App\Entity\Ville;
use App\Enum\NatureProductionType;
use Doctrine\Persistence\ManagerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CrudTableController extends AbstractController
{
    #[Route('/api/crud/{resource}/bulk-delete', name: 'api.crud.bulk_delete', methods: ['POST'])]
    public function bulkDelete(string $resource, Request $request, ManagerRegistry $doctrine): Response
    {
        $definition = $this->getResourceDefinition($resource);
        if ($definition === null) {
            return $this->json(['success' => false, 'message' => 'Ressource invalide.'], 404);
        }

        $payload = $this->parsePayload($request);
        if (!$this->isCsrfTokenValid('crud_bulk_delete_' . $resource, (string) ($payload['_token'] ?? ''))) {
            return $this->json(['success' => false, 'message' => 'Jeton de securite invalide.'], 403);
        }

        $currentDossier = $this->getCurrentDossier();
        if (($definition['scope'] ?? 'global') === 'dossier' && !$currentDossier instanceof Dossier) {
            return $this->json(['success' => false, 'message' => 'Aucun dossier courant selectionne.'], 403);
        }

        $rawIds = $payload['ids'] ?? [];
        if (!is_array($rawIds)) {
            $rawIds = [$rawIds];
        }

        $ids = [];
        foreach ($rawIds as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);

        if ($ids === []) {
            return $this->json(['success' => false, 'message' => 'Aucune ligne selectionnee.'], 422);
        }

        $repository = $doctrine->getRepository($definition['entity']);
        $qb = $repository->createQueryBuilder('e')
            ->andWhere('e.id IN (:ids)')
            ->setParameter('ids', $ids);

        if (($definition['scope'] ?? 'global') === 'dossier') {
            $qb->andWhere('e.dossier = :dossier')->setParameter('dossier', $currentDossier);
        }

        $items = $qb->getQuery()->getResult();
        if (!is_array($items) || $items === []) {
            return $this->json(['success' => false, 'message' => 'Aucun element correspondant a votre selection.'], 404);
        }

        $manager = $doctrine->getManager();
        $deletedIds = [];
        foreach ($items as $item) {
            $id = method_exists($item, 'getId') ? (int) $item->getId() : 0;
            if ($id <= 0) {
                continue;
            }
            $deletedIds[] = $id;
            $manager->remove($item);
        }

        if ($deletedIds === []) {
            return $this->json(['success' => false, 'message' => 'Aucun element supprimable trouve.'], 404);
        }

        $manager->flush();

        $deletedCount = count($deletedIds);
        $skippedCount = max(count($ids) - $deletedCount, 0);
        $message = $deletedCount > 1
            ? sprintf('%d elements supprimes avec succes.', $deletedCount)
            : '1 element supprime avec succes.';

        if ($skippedCount > 0) {
            $message .= ' ' . sprintf('%d element(s) ont ete ignores.', $skippedCount);
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

    #[Route('/api/crud/{resource}/create', name: 'api.crud.create', methods: ['POST'])]
    public function create(string $resource, Request $request, ManagerRegistry $doctrine): Response
    {
        $definition = $this->getResourceDefinition($resource);
        if ($definition === null || ($definition['simpleCrud'] ?? false) !== true) {
            return $this->json(['success' => false, 'message' => 'Creation rapide indisponible.'], 404);
        }

        $payload = $this->parsePayload($request);
        if (!$this->isCsrfTokenValid('crud_create_' . $resource, (string) ($payload['_token'] ?? ''))) {
            return $this->json(['success' => false, 'message' => 'Jeton de securite invalide.'], 403);
        }

        $currentDossier = $this->getCurrentDossier();
        if (($definition['scope'] ?? 'global') === 'dossier' && !$currentDossier instanceof Dossier) {
            return $this->json(['success' => false, 'message' => 'Aucun dossier courant selectionne.'], 403);
        }

        $entityClass = $definition['entity'];
        $entity = new $entityClass();
        if (($definition['scope'] ?? 'global') === 'dossier' && method_exists($entity, 'setDossier')) {
            $entity->setDossier($currentDossier);
        }

        $error = $this->hydrateEntity($entity, $definition, $payload, $doctrine);
        if ($error !== null) {
            return $this->json(['success' => false, 'message' => $error], 422);
        }

        // Special handling for Entetepiece: auto-generate pieceno, validate code operation, and set default statut
        if ($resource === 'entetepiece' && $entity instanceof Entetepiece && $currentDossier instanceof Dossier) {
            if ($entity->getPieceno() === null) {
                $lastPieceNo = $doctrine->getRepository(Entetepiece::class)
                    ->createQueryBuilder('e')
                    ->select('MAX(e.pieceno)')
                    ->where('e.dossier = :dossier')
                    ->setParameter('dossier', $currentDossier)
                    ->getQuery()
                    ->getSingleScalarResult();
                $nextPieceNo = ($lastPieceNo ?? 0) + 1;
                $entity->setPieceno($nextPieceNo);
            }
            if ($entity->getStatut() === null) {
                $entity->setStatut('Brouillon');
            }
            
            // Auto-resolve code operation if invalid or not set
            $codeOp = $entity->getCodeOperation();
            if ($codeOp === null || !$codeOp->getIsActive()) {
                $pieceType = $entity->getType();
                $tierType = $entity->getTypet();
                
                // Find the appropriate code operation based on tier type
                $coRepo = $doctrine->getRepository(\App\Entity\CodeOperation::class);
                $operationLibelle = null;
                
                if (in_array(strtolower($tierType), ['client', 'prospect'], true)) {
                    $operationLibelle = 'Vente Standard';
                } elseif (strtolower($tierType) === 'fournisseur') {
                    $operationLibelle = 'Achat Standard';
                } elseif (in_array(strtolower($tierType), ['interne', 'tiersinterne', 'tiers interne'], true)) {
                    $operationLibelle = 'Transfert Interne Sortie';
                }
                
                if ($operationLibelle !== null) {
                    $resolvedCodeOp = $coRepo->findOneBy(['libelle' => $operationLibelle, 'is_active' => true]);
                    if ($resolvedCodeOp !== null) {
                        $entity->setCodeOperation($resolvedCodeOp);
                    }
                }
            }
        }

        $this->applyAuditContext($entity, $doctrine);

        $manager = $doctrine->getManager();
        $manager->persist($entity);
        $manager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Element cree avec succes.',
            'item' => $this->serializeItem($resource, $entity),
        ]);
    }

    #[Route('/api/crud/{resource}/{id<\d+>}/update', name: 'api.crud.update', methods: ['POST'])]
    public function update(string $resource, int $id, Request $request, ManagerRegistry $doctrine): Response
    {
        $definition = $this->getResourceDefinition($resource);
        if ($definition === null || ($definition['simpleCrud'] ?? false) !== true) {
            return $this->json(['success' => false, 'message' => 'Edition inline indisponible.'], 404);
        }

        $payload = $this->parsePayload($request);
        if (!$this->isCsrfTokenValid('crud_update_' . $resource, (string) ($payload['_token'] ?? ''))) {
            return $this->json(['success' => false, 'message' => 'Jeton de securite invalide.'], 403);
        }

        $entity = $this->findScopedEntity($resource, $id, $doctrine);
        if ($entity === null) {
            return $this->json(['success' => false, 'message' => 'Element introuvable.'], 404);
        }

        $error = $this->hydrateEntity($entity, $definition, $payload, $doctrine, true);
        if ($error !== null) {
            return $this->json(['success' => false, 'message' => $error], 422);
        }

        $this->applyAuditContext($entity, $doctrine);
        $doctrine->getManager()->flush();

        return $this->json([
            'success' => true,
            'message' => 'Element mis a jour avec succes.',
            'item' => $this->serializeItem($resource, $entity),
        ]);
    }

    #[Route('/api/crud/{resource}/import', name: 'api.crud.import', methods: ['POST'])]
    public function import(string $resource, Request $request, ManagerRegistry $doctrine): Response
    {
        $definition = $this->getResourceDefinition($resource);
        if ($definition === null || ($definition['simpleCrud'] ?? false) !== true) {
            return $this->json(['success' => false, 'message' => 'Import indisponible pour cette ressource.'], 404);
        }

        if (!$this->isCsrfTokenValid('crud_import_' . $resource, (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de securite invalide.'], 403);
        }

        $currentDossier = $this->getCurrentDossier();
        if (($definition['scope'] ?? 'global') === 'dossier' && !$currentDossier instanceof Dossier) {
            return $this->json(['success' => false, 'message' => 'Aucun dossier courant selectionne.'], 403);
        }

        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            return $this->json(['success' => false, 'message' => 'Veuillez selectionner un fichier valide.'], 422);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt', 'xls', 'xlsx'], true)) {
            return $this->json(['success' => false, 'message' => 'Format non supporte. Utilisez CSV, XLS ou XLSX.'], 422);
        }

        try {
            $rows = $this->parseImportedRows((string) $file->getRealPath(), $definition);
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de lire ce fichier d import. Verifiez le format et les en-tetes.',
            ], 422);
        }

        if ($rows === []) {
            return $this->json(['success' => false, 'message' => 'Aucune ligne exploitable trouvee dans le fichier.'], 422);
        }

        $manager = $doctrine->getManager();
        $createdCount = 0;
        $updatedCount = 0;
        $errors = [];

        foreach ($rows as $index => $rowPayload) {
            $lineNumber = $index + 2;
            $id = isset($rowPayload['id']) ? (int) $rowPayload['id'] : 0;

            if ($id > 0) {
                $entity = $this->findScopedEntity($resource, $id, $doctrine);
                if ($entity === null) {
                    $errors[] = sprintf('Ligne %d: element #%d introuvable.', $lineNumber, $id);
                    continue;
                }

                foreach ($definition['fields'] as $fieldName => $fieldConfig) {
                    if (array_key_exists($fieldName, $rowPayload)) {
                        continue;
                    }

                    $getter = (string) ($fieldConfig['getter'] ?? '');
                    if ($getter !== '' && method_exists($entity, $getter)) {
                        $rowPayload[$fieldName] = $entity->{$getter}();
                    }
                }

                $updatedCount++;
            } else {
                $entityClass = $definition['entity'];
                $entity = new $entityClass();
                if (($definition['scope'] ?? 'global') === 'dossier' && method_exists($entity, 'setDossier')) {
                    $entity->setDossier($currentDossier);
                }
                $createdCount++;
            }

            $error = $this->hydrateEntity($entity, $definition, $rowPayload, $doctrine);
            if ($error !== null) {
                $errors[] = sprintf('Ligne %d: %s', $lineNumber, $error);
                if ($id <= 0) {
                    $createdCount = max(0, $createdCount - 1);
                } else {
                    $updatedCount = max(0, $updatedCount - 1);
                }
                continue;
            }

            $this->applyAuditContext($entity, $doctrine);
            $manager->persist($entity);
        }

        if ($createdCount === 0 && $updatedCount === 0) {
            return $this->json([
                'success' => false,
                'message' => 'Aucune ligne importee.',
                'errors' => $errors,
            ], 422);
        }

        try {
            $manager->flush();
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l enregistrement des donnees importees.',
            ], 500);
        }

        $summary = sprintf('Import termine: %d cree(s), %d mis a jour.', $createdCount, $updatedCount);
        if ($errors !== []) {
            $summary .= ' Certaines lignes ont ete ignorees.';
        }

        return $this->json([
            'success' => true,
            'message' => $summary,
            'createdCount' => $createdCount,
            'updatedCount' => $updatedCount,
            'errorCount' => count($errors),
            'errors' => $errors,
        ]);
    }

    private function getResourceDefinition(string $resource): ?array
    {
        $definitions = [
            'pays' => [
                'entity' => Pays::class,
                'scope' => 'global',
                'simpleCrud' => true,
                'edit_route' => 'pays.edit',
                'delete_route' => 'pays.delete',
                'fields' => [
                    'libelle' => ['setter' => 'setLibelle', 'getter' => 'getLibelle', 'type' => 'string', 'required' => true],
                ],
            ],
            'ville' => [
                'entity' => Ville::class,
                'scope' => 'global',
                'simpleCrud' => true,
                'edit_route' => 'ville.edit',
                'delete_route' => 'ville.delete',
                'fields' => [
                    'libelle' => ['setter' => 'setLibelle', 'getter' => 'getLibelle', 'type' => 'string', 'required' => true],
                ],
            ],
            'unite' => [
                'entity' => Unite::class,
                'scope' => 'global',
                'simpleCrud' => true,
                'edit_route' => 'unite.edit',
                'delete_route' => 'unite.delete',
                'fields' => [
                    'code' => ['setter' => 'setCode', 'getter' => 'getCode', 'type' => 'string', 'required' => false],
                    'libelle' => ['setter' => 'setLibelle', 'getter' => 'getLibelle', 'type' => 'string', 'required' => true],
                ],
            ],
            'devise' => [
                'entity' => Devises::class,
                'scope' => 'global',
                'simpleCrud' => true,
                'edit_route' => 'devise.edit',
                'delete_route' => 'devise.delete',
                'fields' => [
                    'code' => ['setter' => 'setCode', 'getter' => 'getCode', 'type' => 'string', 'required' => true],
                    'libelle' => ['setter' => 'setLibelle', 'getter' => 'getLibelle', 'type' => 'string', 'required' => true],
                ],
            ],
            'reglement' => [
                'entity' => Reglement::class,
                'scope' => 'global',
                'simpleCrud' => true,
                'edit_route' => 'reglement.edit',
                'delete_route' => 'reglement.delete',
                'fields' => [
                    'libelle' => ['setter' => 'setLibelle', 'getter' => 'getLibelle', 'type' => 'string', 'required' => true],
                    'echeance' => ['setter' => 'setEcheance', 'getter' => 'getEcheance', 'type' => 'int', 'required' => false],
                ],
            ],
            'tarif' => [
                'entity' => Tarifs::class,
                'scope' => 'dossier',
                'simpleCrud' => true,
                'edit_route' => 'tarif.edit',
                'delete_route' => 'tarif.delete',
                'fields' => [
                    'libelle' => ['setter' => 'setLibelle', 'getter' => 'getLibelle', 'type' => 'string', 'required' => true],
                ],
            ],
            'dossier' => [
                'entity' => Dossier::class,
                'scope' => 'global',
                'simpleCrud' => true,
                'edit_route' => 'dossier.edit',
                'delete_route' => 'dossier.delete',
                'fields' => [
                    'nom' => ['setter' => 'setNom', 'getter' => 'getNom', 'type' => 'string', 'required' => true],
                    'adresse' => ['setter' => 'setAdresse', 'getter' => 'getAdresse', 'type' => 'string', 'required' => false],
                ],
            ],
            'client' => [
                'entity' => Clients::class,
                'scope' => 'dossier',
                'simpleCrud' => true,
                'edit_route' => 'client.edit',
                'delete_route' => 'client.delete',
                'fields' => [
                    'nom' => ['setter' => 'setNom', 'getter' => 'getNom', 'type' => 'string', 'required' => true],
                    'adr1' => ['setter' => 'setAdr1', 'getter' => 'getAdr1', 'type' => 'string', 'required' => true],
                    'adr2' => ['setter' => 'setAdr2', 'getter' => 'getAdr2', 'type' => 'string', 'required' => false],
                    'rue' => ['setter' => 'setRue', 'getter' => 'getRue', 'type' => 'string', 'required' => false, 'fallbackFrom' => 'adr1'],
                    'codepostal' => ['setter' => 'setCodepostal', 'getter' => 'getCodepostal', 'type' => 'int', 'required' => false],
                    'ville' => ['setter' => 'setVille', 'getter' => 'getVille', 'type' => 'entity', 'entity' => Ville::class, 'lookup' => 'libelle', 'required' => false],
                    'pays' => ['setter' => 'setPays', 'getter' => 'getPays', 'type' => 'entity', 'entity' => Pays::class, 'lookup' => 'libelle', 'required' => false],
                    'tel' => ['setter' => 'setTel', 'getter' => 'getTel', 'type' => 'string', 'required' => false],
                    'email' => ['setter' => 'setEmail', 'getter' => 'getEmail', 'type' => 'string', 'required' => false],
                    'web' => ['setter' => 'setWeb', 'getter' => 'getWeb', 'type' => 'string', 'required' => false],
                    'linkedin' => ['setter' => 'setLinkedin', 'getter' => 'getLinkedin', 'type' => 'string', 'required' => false],
                    'tarif' => ['setter' => 'setTarif', 'getter' => 'getTarif', 'type' => 'entity', 'entity' => Tarifs::class, 'lookup' => 'libelle', 'scope' => 'dossier', 'required' => false],
                ],
            ],
            'prospect' => [
                'entity' => Prospects::class,
                'scope' => 'dossier',
                'simpleCrud' => true,
                'edit_route' => 'prospect.edit',
                'delete_route' => 'prospect.delete',
                'fields' => [
                    'nom' => ['setter' => 'setNom', 'getter' => 'getNom', 'type' => 'string', 'required' => true],
                    'adr1' => ['setter' => 'setAdr1', 'getter' => 'getAdr1', 'type' => 'string', 'required' => true],
                    'adr2' => ['setter' => 'setAdr2', 'getter' => 'getAdr2', 'type' => 'string', 'required' => false],
                    'rue' => ['setter' => 'setRue', 'getter' => 'getRue', 'type' => 'string', 'required' => false, 'fallbackFrom' => 'adr1'],
                    'codepostal' => ['setter' => 'setCodepostal', 'getter' => 'getCodepostal', 'type' => 'int', 'required' => false],
                    'ville' => ['setter' => 'setVille', 'getter' => 'getVille', 'type' => 'entity', 'entity' => Ville::class, 'lookup' => 'libelle', 'required' => false],
                    'pays' => ['setter' => 'setPays', 'getter' => 'getPays', 'type' => 'entity', 'entity' => Pays::class, 'lookup' => 'libelle', 'required' => true],
                    'tel' => ['setter' => 'setTel', 'getter' => 'getTel', 'type' => 'string', 'required' => false],
                    'email' => ['setter' => 'setEmail', 'getter' => 'getEmail', 'type' => 'string', 'required' => false],
                    'web' => ['setter' => 'setWeb', 'getter' => 'getWeb', 'type' => 'string', 'required' => false],
                    'linkedin' => ['setter' => 'setLinkedin', 'getter' => 'getLinkedin', 'type' => 'string', 'required' => false],
                ],
            ],
            'fournisseur' => [
                'entity' => Fournisseur::class,
                'scope' => 'dossier',
                'simpleCrud' => true,
                'edit_route' => 'fournisseur.edit',
                'delete_route' => 'fournisseur.delete',
                'fields' => [
                    'nom' => ['setter' => 'setNom', 'getter' => 'getNom', 'type' => 'string', 'required' => true],
                    'adr1' => ['setter' => 'setAdr1', 'getter' => 'getAdr1', 'type' => 'string', 'required' => true],
                    'adr2' => ['setter' => 'setAdr2', 'getter' => 'getAdr2', 'type' => 'string', 'required' => false],
                    'rue' => ['setter' => 'setRue', 'getter' => 'getRue', 'type' => 'string', 'required' => false, 'fallbackFrom' => 'adr1'],
                    'codepostal' => ['setter' => 'setCodepostal', 'getter' => 'getCodepostal', 'type' => 'int', 'required' => false],
                    'ville' => ['setter' => 'setVille', 'getter' => 'getVille', 'type' => 'entity', 'entity' => Ville::class, 'lookup' => 'libelle', 'required' => false],
                    'pays' => ['setter' => 'setPays', 'getter' => 'getPays', 'type' => 'entity', 'entity' => Pays::class, 'lookup' => 'libelle', 'required' => false],
                    'tel' => ['setter' => 'setTel', 'getter' => 'getTel', 'type' => 'string', 'required' => false],
                    'email' => ['setter' => 'setEmail', 'getter' => 'getEmail', 'type' => 'string', 'required' => false],
                    'web' => ['setter' => 'setWeb', 'getter' => 'getWeb', 'type' => 'string', 'required' => false],
                    'linkedin' => ['setter' => 'setLinkedin', 'getter' => 'getLinkedin', 'type' => 'string', 'required' => false],
                    'tarif' => ['setter' => 'setTarif', 'getter' => 'getTarif', 'type' => 'entity', 'entity' => Tarifs::class, 'lookup' => 'libelle', 'scope' => 'dossier', 'required' => false],
                ],
            ],
            'tarifvente' => [
                'entity' => Tarifvente::class,
                'scope' => 'dossier',
                'simpleCrud' => true,
                'edit_route' => 'tarifvente.edit',
                'delete_route' => 'tarifvente.delete',
                'fields' => [
                    'tarif' => ['setter' => 'setTarif', 'getter' => 'getTarif', 'type' => 'entity', 'entity' => Tarifs::class, 'lookup' => 'libelle', 'scope' => 'dossier', 'required' => false],
                    'client' => ['setter' => 'setClient', 'getter' => 'getClient', 'type' => 'entity', 'entity' => Clients::class, 'lookup' => 'nom', 'scope' => 'dossier', 'required' => true],
                    'article' => ['setter' => 'setArticle', 'getter' => 'getArticle', 'type' => 'entity', 'entity' => Article::class, 'lookup' => 'libelle', 'scope' => 'dossier', 'required' => true],
                    'devise' => ['setter' => 'setDevise', 'getter' => 'getDevise', 'type' => 'entity', 'entity' => Devises::class, 'lookup' => 'libelle', 'required' => false],
                    'dateeffet' => ['setter' => 'setDateeffet', 'getter' => 'getDateeffet', 'type' => 'date', 'required' => false],
                    'prix' => ['setter' => 'setPrix', 'getter' => 'getPrix', 'type' => 'float', 'required' => false],
                ],
            ],
            'depot' => [
                'entity' => Depot::class,
                'scope' => 'dossier',
                'simpleCrud' => true,
                'edit_route' => 'app_depot_edit',
                'delete_route' => 'app_depot_delete',
                'fields' => [
                    'libelle' => ['setter' => 'setLibelle', 'getter' => 'getLibelle', 'type' => 'string', 'required' => true],
                    'tiersInterne' => ['setter' => 'setTiersInterne', 'getter' => 'getTiersInterne', 'type' => 'entity', 'entity' => TiersInterne::class, 'lookup' => 'nom', 'scope' => 'dossier', 'required' => false],
                    'adr1' => ['setter' => 'setAdr1', 'getter' => 'getAdr1', 'type' => 'string', 'required' => false],
                    'adr2' => ['setter' => 'setAdr2', 'getter' => 'getAdr2', 'type' => 'string', 'required' => false],
                    'rue' => ['setter' => 'setRue', 'getter' => 'getRue', 'type' => 'string', 'required' => false],
                    'codepostal' => ['setter' => 'setCodepostal', 'getter' => 'getCodepostal', 'type' => 'string', 'required' => false],
                    'ville' => ['setter' => 'setVille', 'getter' => 'getVille', 'type' => 'entity', 'entity' => Ville::class, 'lookup' => 'libelle', 'required' => false],
                    'pays' => ['setter' => 'setPays', 'getter' => 'getPays', 'type' => 'entity', 'entity' => Pays::class, 'lookup' => 'libelle', 'required' => false],
                ],
            ],
            'tiers_interne' => [
                'entity' => TiersInterne::class,
                'scope' => 'dossier',
                'simpleCrud' => true,
                'edit_route' => 'app_tiers_interne_edit',
                'delete_route' => 'app_tiers_interne_delete',
                'fields' => [
                    'nom' => ['setter' => 'setNom', 'getter' => 'getNom', 'type' => 'string', 'required' => true],
                    'adr1' => ['setter' => 'setAdr1', 'getter' => 'getAdr1', 'type' => 'string', 'required' => true],
                    'adr2' => ['setter' => 'setAdr2', 'getter' => 'getAdr2', 'type' => 'string', 'required' => false],
                    'rue' => ['setter' => 'setRue', 'getter' => 'getRue', 'type' => 'string', 'required' => false, 'fallbackFrom' => 'adr1'],
                    'codepostal' => ['setter' => 'setCodepostal', 'getter' => 'getCodepostal', 'type' => 'int', 'required' => false],
                    'ville' => ['setter' => 'setVille', 'getter' => 'getVille', 'type' => 'entity', 'entity' => Ville::class, 'lookup' => 'libelle', 'required' => false],
                    'pays' => ['setter' => 'setPays', 'getter' => 'getPays', 'type' => 'entity', 'entity' => Pays::class, 'lookup' => 'libelle', 'required' => false],
                    'tel' => ['setter' => 'setTel', 'getter' => 'getTel', 'type' => 'string', 'required' => false],
                    'email' => ['setter' => 'setEmail', 'getter' => 'getEmail', 'type' => 'string', 'required' => false],
                    'web' => ['setter' => 'setWeb', 'getter' => 'getWeb', 'type' => 'string', 'required' => false],
                    'linkedin' => ['setter' => 'setLinkedin', 'getter' => 'getLinkedin', 'type' => 'string', 'required' => false],
                    'tarif' => ['setter' => 'setTarif', 'getter' => 'getTarif', 'type' => 'entity', 'entity' => Tarifs::class, 'lookup' => 'libelle', 'scope' => 'dossier', 'required' => false],
                ],
            ],
            'nature_production' => [
                'entity' => NatureProduction::class,
                'scope' => 'global',
                'simpleCrud' => true,
                'edit_route' => 'app_nature_production_edit',
                'delete_route' => 'app_nature_production_delete',
                'fields' => [
                    'libelle' => ['setter' => 'setLibelle', 'getter' => 'getLibelle', 'type' => 'string', 'required' => true],
                    'type' => ['setter' => 'setType', 'getter' => 'getType', 'type' => 'enum', 'enumClass' => NatureProductionType::class, 'required' => true],
                ],
            ],
            'entetepiece' => [
                'entity' => Entetepiece::class,
                'scope' => 'dossier',
                'simpleCrud' => true,
                'edit_route' => 'entetepiece.edit',
                'delete_route' => 'entetepiece.delete',
                'fields' => [
                    'type' => ['setter' => 'setType', 'getter' => 'getType', 'type' => 'string', 'required' => true],
                    'typet' => ['setter' => 'setTypet', 'getter' => 'getTypet', 'type' => 'string', 'required' => true],
                    'client' => ['setter' => 'setClient', 'getter' => 'getClient', 'type' => 'entity', 'entity' => Clients::class, 'lookup' => 'nom', 'scope' => 'dossier', 'required' => true],
                    'code_operation' => ['setter' => 'setCodeOperation', 'getter' => 'getCodeOperation', 'type' => 'entity', 'entity' => CodeOperation::class, 'lookup' => 'libelle', 'required' => true],
                    'pieceref' => ['setter' => 'setPieceref', 'getter' => 'getPieceref', 'type' => 'string', 'required' => false],
                    'remise' => ['setter' => 'setRemise', 'getter' => 'getRemise', 'type' => 'float', 'required' => false],
                    'devise' => ['setter' => 'setDevise', 'getter' => 'getDevise', 'type' => 'entity', 'entity' => Devises::class, 'lookup' => 'libelle', 'required' => false],
                    'reglement' => ['setter' => 'setReglement', 'getter' => 'getReglement', 'type' => 'entity', 'entity' => Reglement::class, 'lookup' => 'libelle', 'required' => false],
                    'statut' => ['setter' => 'setStatut', 'getter' => 'getStatut', 'type' => 'string', 'required' => false],
                ],
            ],
        ];

        return $definitions[$resource] ?? null;
    }

    private function parsePayload(Request $request): array
    {
        $payload = $request->request->all();
        if ($payload === []) {
            $decoded = json_decode((string) $request->getContent(), true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return is_array($payload) ? $payload : [];
    }

    private function getCurrentDossier(): ?Dossier
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->getCurrentDossier() : null;
    }

    private function hydrateEntity(object $entity, array $definition, array $payload, ManagerRegistry $doctrine, bool $partialUpdate = false): ?string
    {
        $currentDossier = $this->getCurrentDossier();

        foreach ($definition['fields'] as $fieldName => $fieldConfig) {
            $setter = (string) $fieldConfig['setter'];
            if (!method_exists($entity, $setter)) {
                continue;
            }

            if ($partialUpdate && !array_key_exists($fieldName, $payload)) {
                continue;
            }

            $rawValue = $payload[$fieldName] ?? null;
            $value = is_string($rawValue) ? trim($rawValue) : $rawValue;
            $fallbackFrom = (string) ($fieldConfig['fallbackFrom'] ?? '');
            if (($value === null || $value === '') && $fallbackFrom !== '' && array_key_exists($fallbackFrom, $payload)) {
                $fallbackValue = $payload[$fallbackFrom];
                $value = is_string($fallbackValue) ? trim($fallbackValue) : $fallbackValue;
            }

            $isRequired = (bool) ($fieldConfig['required'] ?? false);
            $type = (string) ($fieldConfig['type'] ?? 'string');

            if ($isRequired && ($value === null || $value === '')) {
                return sprintf('Le champ %s est obligatoire.', $fieldName);
            }

            if ($type === 'int') {
                if ($value === null || $value === '') {
                    $value = null;
                } else {
                    $normalized = $this->normalizeNumber((string) $value);
                    if (!is_numeric($normalized)) {
                        return sprintf('Le champ %s doit etre numerique.', $fieldName);
                    }
                    $value = (int) $normalized;
                }
            } elseif ($type === 'float') {
                if ($value === null || $value === '') {
                    $value = null;
                } else {
                    $normalized = $this->normalizeNumber((string) $value);
                    if (!is_numeric($normalized)) {
                        return sprintf('Le champ %s doit etre numerique.', $fieldName);
                    }
                    $value = (float) $normalized;
                }
            } elseif ($type === 'date') {
                if ($value === null || $value === '') {
                    $value = null;
                } else {
                    $parsedDate = $this->parseFlexibleDate((string) $value);
                    if (!$parsedDate instanceof \DateTimeInterface) {
                        return sprintf('Le champ %s contient une date invalide.', $fieldName);
                    }
                    $value = $parsedDate;
                }
            } elseif ($type === 'entity') {
                $entityClass = (string) ($fieldConfig['entity'] ?? '');
                if ($entityClass === '') {
                    return sprintf('Configuration invalide pour le champ %s.', $fieldName);
                }

                if ($value === null || $value === '') {
                    if ($isRequired) {
                        return sprintf('Le champ %s est obligatoire.', $fieldName);
                    }
                    $value = null;
                } else {
                    $resolvedEntity = $this->resolveEntityReference(
                        (string) $value,
                        $entityClass,
                        (string) ($fieldConfig['lookup'] ?? 'libelle'),
                        (string) ($fieldConfig['scope'] ?? ''),
                        $currentDossier,
                        $doctrine
                    );

                    if ($resolvedEntity === null) {
                        return sprintf('Valeur invalide pour le champ %s.', $fieldName);
                    }

                    $value = $resolvedEntity;
                }
            } elseif ($type === 'enum') {
                $enumClass = (string) ($fieldConfig['enumClass'] ?? '');
                if ($enumClass === '' || !enum_exists($enumClass)) {
                    return sprintf('Configuration invalide pour le champ %s.', $fieldName);
                }

                if ($value === null || $value === '') {
                    if ($isRequired) {
                        return sprintf('Le champ %s est obligatoire.', $fieldName);
                    }
                    $value = null;
                } else {
                    $resolvedEnum = $this->resolveEnumValue((string) $value, $enumClass);
                    if ($resolvedEnum === null) {
                        return sprintf('Valeur invalide pour le champ %s.', $fieldName);
                    }
                    $value = $resolvedEnum;
                }
            } elseif ($type === 'string') {
                $value = $value === null ? null : (string) $value;
                if ($value === '' && !$isRequired) {
                    $value = null;
                }
            }

            if ($isRequired && $value === null) {
                return sprintf('Le champ %s est obligatoire.', $fieldName);
            }

            if ($value === null && $this->setterDisallowsNull($entity, $setter)) {
                if ($isRequired) {
                    return sprintf('Le champ %s est obligatoire.', $fieldName);
                }
                $value = '';
            }

            try {
                $entity->{$setter}($value);
            } catch (\TypeError $typeError) {
                return sprintf('Valeur invalide pour le champ %s.', $fieldName);
            }
        }

        return null;
    }

    private function serializeItem(string $resource, object $entity): array
    {
        $definition = $this->getResourceDefinition($resource);
        $fields = [];

        foreach ($definition['fields'] as $fieldName => $fieldConfig) {
            $getter = (string) $fieldConfig['getter'];
            $value = method_exists($entity, $getter) ? $entity->{$getter}() : null;
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            } elseif ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif (is_object($value)) {
                $value = method_exists($value, '__toString') ? (string) $value : null;
            }
            $fields[$fieldName] = $value;
        }

        $id = method_exists($entity, 'getId') ? $entity->getId() : null;

        return [
            'id' => $id,
            'fields' => $fields,
            'editUrl' => isset($definition['edit_route']) && $id ? $this->generateUrl($definition['edit_route'], ['id' => $id]) : null,
            'deleteUrl' => isset($definition['delete_route']) && $id ? $this->generateUrl($definition['delete_route'], ['id' => $id]) : null,
        ];
    }

    private function applyAuditContext(object $entity, ManagerRegistry $doctrine): void
    {
        if (method_exists($entity, 'setDoctrine')) {
            $entity->setDoctrine($doctrine);
        }

        $user = $this->getUser();
        if ($user instanceof User && method_exists($entity, 'setUser')) {
            $entity->setUser($user);
        }
    }

    private function findScopedEntity(string $resource, int $id, ManagerRegistry $doctrine): ?object
    {
        $definition = $this->getResourceDefinition($resource);
        if ($definition === null) {
            return null;
        }

        $currentDossier = $this->getCurrentDossier();
        if (($definition['scope'] ?? 'global') === 'dossier' && !$currentDossier instanceof Dossier) {
            return null;
        }

        $repository = $doctrine->getRepository($definition['entity']);
        if (($definition['scope'] ?? 'global') !== 'dossier') {
            return $repository->find($id);
        }

        return $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);
    }

    private function resolveEntityReference(
        string $rawValue,
        string $entityClass,
        string $lookupField,
        string $scope,
        ?Dossier $currentDossier,
        ManagerRegistry $doctrine
    ): ?object {
        $value = trim($rawValue);
        if ($value === '') {
            return null;
        }

        $repository = $doctrine->getRepository($entityClass);
        $scopeCriteria = [];
        if ($scope === 'dossier') {
            if (!$currentDossier instanceof Dossier) {
                return null;
            }
            $scopeCriteria['dossier'] = $currentDossier;
        }

        if (is_numeric($value)) {
            $id = (int) $value;
            if ($id <= 0) {
                return null;
            }
            $criteria = array_merge(['id' => $id], $scopeCriteria);
            $foundById = $repository->findOneBy($criteria);
            if ($foundById !== null) {
                return $foundById;
            }
        }

        $lookup = trim($lookupField);
        if ($lookup === '') {
            $lookup = 'libelle';
        }

        $criteria = array_merge([$lookup => $value], $scopeCriteria);
        return $repository->findOneBy($criteria);
    }

    private function resolveEnumValue(string $rawValue, string $enumClass): ?\BackedEnum
    {
        $value = trim($rawValue);
        if ($value === '') {
            return null;
        }

        $upper = strtoupper($value);
        foreach ($enumClass::cases() as $case) {
            if ($case instanceof \BackedEnum) {
                $caseValue = strtoupper((string) $case->value);
                if ($caseValue === $upper) {
                    return $case;
                }
            }

            if (strtoupper($case->name) === $upper) {
                return $case;
            }
        }

        return null;
    }

    private function parseFlexibleDate(string $rawValue): ?\DateTimeImmutable
    {
        $value = trim($rawValue);
        if ($value === '') {
            return null;
        }

        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y', 'Y/m/d'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp($timestamp);
    }

    private function normalizeNumber(string $rawValue): string
    {
        $value = trim($rawValue);
        if ($value === '') {
            return '';
        }

        $value = str_replace([' ', "\xc2\xa0"], '', $value);
        if (substr_count($value, ',') > 0 && substr_count($value, '.') === 0) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return $value;
    }

    private function setterDisallowsNull(object $entity, string $setter): bool
    {
        try {
            $method = new \ReflectionMethod($entity, $setter);
        } catch (\ReflectionException $exception) {
            return false;
        }

        $parameters = $method->getParameters();
        if ($parameters === [] || !isset($parameters[0])) {
            return false;
        }

        return !$parameters[0]->allowsNull();
    }

    private function parseImportedRows(string $filePath, array $definition): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if (!is_array($sheetRows) || count($sheetRows) < 2) {
            return [];
        }

        $headerRow = $sheetRows[0];
        $fieldAliases = $this->buildImportFieldAliases($definition);
        $columnToField = [];

        foreach ($headerRow as $colIndex => $headerValue) {
            $normalized = $this->normalizeImportKey((string) $headerValue);
            if ($normalized === '' || !isset($fieldAliases[$normalized])) {
                continue;
            }
            $columnToField[(int) $colIndex] = $fieldAliases[$normalized];
        }

        if ($columnToField === []) {
            return [];
        }

        $rows = [];
        foreach (array_slice($sheetRows, 1) as $rawRow) {
            $payload = [];
            $hasData = false;

            foreach ($columnToField as $colIndex => $fieldName) {
                $value = $rawRow[$colIndex] ?? null;
                if (is_string($value)) {
                    $value = trim($value);
                }

                if ($fieldName === 'id') {
                    $payload['id'] = $value;
                    if ($value !== null && $value !== '') {
                        $hasData = true;
                    }
                    continue;
                }

                $payload[$fieldName] = $value;
                if ($value !== null && $value !== '') {
                    $hasData = true;
                }
            }

            if ($hasData) {
                $rows[] = $payload;
            }
        }

        return $rows;
    }

    private function buildImportFieldAliases(array $definition): array
    {
        $aliases = [
            'id' => 'id',
            'identifiant' => 'id',
        ];

        foreach (array_keys($definition['fields'] ?? []) as $fieldName) {
            $normalizedField = $this->normalizeImportKey($fieldName);
            if ($normalizedField === '') {
                continue;
            }
            $aliases[$normalizedField] = $fieldName;
        }

        $customAliases = [
            'libelle' => 'libelle',
            'label' => 'libelle',
            'designation' => 'libelle',
            'nom' => 'nom',
            'name' => 'nom',
            'rue' => 'rue',
            'codepostal' => 'codepostal',
            'cp' => 'codepostal',
            'code' => 'code',
            'echeance' => 'echeance',
            'delai' => 'echeance',
            'jours' => 'echeance',
            'ville' => 'ville',
            'pays' => 'pays',
            'tarif' => 'tarif',
            'client' => 'client',
            'article' => 'article',
            'devise' => 'devise',
            'prix' => 'prix',
            'dateeffet' => 'dateeffet',
            'type' => 'type',
            'tiersinterne' => 'tiersInterne',
        ];

        if (isset($definition['fields']['adresse'])) {
            $aliases['adresse'] = 'adresse';
            $aliases['address'] = 'adresse';
        }
        if (isset($definition['fields']['adr1'])) {
            $aliases['adresse'] = 'adr1';
            $aliases['address'] = 'adr1';
        }

        foreach ($customAliases as $alias => $fieldName) {
            if (isset($definition['fields'][$fieldName])) {
                $aliases[$alias] = $fieldName;
            }
        }

        return $aliases;
    }

    private function normalizeImportKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($ascii) && $ascii !== '') {
            $normalized = strtolower($ascii);
        }

        return preg_replace('/[^a-z0-9]/', '', $normalized) ?? '';
    }
}
