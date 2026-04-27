<?php

namespace App\Controller;

use App\Entity\Clients;
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

        $error = $this->hydrateEntity($entity, $definition, $payload);
        if ($error !== null) {
            return $this->json(['success' => false, 'message' => $error], 422);
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

        $error = $this->hydrateEntity($entity, $definition, $payload);
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

            $error = $this->hydrateEntity($entity, $definition, $rowPayload);
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
                'simpleCrud' => false,
            ],
            'prospect' => [
                'entity' => Prospects::class,
                'scope' => 'dossier',
                'simpleCrud' => false,
            ],
            'tarifvente' => [
                'entity' => Tarifvente::class,
                'scope' => 'dossier',
                'simpleCrud' => false,
            ],
            'entetepiece' => [
                'entity' => Entetepiece::class,
                'scope' => 'dossier',
                'simpleCrud' => false,
            ],
            'fournisseur' => [
                'entity' => Fournisseur::class,
                'scope' => 'dossier',
                'simpleCrud' => false,
            ],
            'depot' => [
                'entity' => Depot::class,
                'scope' => 'dossier',
                'simpleCrud' => false,
            ],
            'tiers_interne' => [
                'entity' => TiersInterne::class,
                'scope' => 'dossier',
                'simpleCrud' => false,
            ],
            'nature_production' => [
                'entity' => NatureProduction::class,
                'scope' => 'global',
                'simpleCrud' => false,
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

    private function hydrateEntity(object $entity, array $definition, array $payload): ?string
    {
        foreach ($definition['fields'] as $fieldName => $fieldConfig) {
            $setter = (string) $fieldConfig['setter'];
            if (!method_exists($entity, $setter)) {
                continue;
            }

            $rawValue = $payload[$fieldName] ?? null;
            $value = is_string($rawValue) ? trim($rawValue) : $rawValue;
            $isRequired = (bool) ($fieldConfig['required'] ?? false);
            $type = (string) ($fieldConfig['type'] ?? 'string');

            if ($isRequired && ($value === null || $value === '')) {
                return sprintf('Le champ %s est obligatoire.', $fieldName);
            }

            if ($type === 'int') {
                if ($value === null || $value === '') {
                    $value = null;
                } elseif (!is_numeric((string) $value)) {
                    return sprintf('Le champ %s doit etre numerique.', $fieldName);
                } else {
                    $value = (int) $value;
                }
            } elseif ($type === 'string') {
                $value = $value === null ? null : (string) $value;
                if ($value === '' && !$isRequired) {
                    $value = null;
                }
            }

            $entity->{$setter}($value);
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
            'adresse' => 'adresse',
            'address' => 'adresse',
            'code' => 'code',
            'echeance' => 'echeance',
            'delai' => 'echeance',
            'jours' => 'echeance',
        ];

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
