<?php

namespace App\Controller;

use App\Entity\Clients;
use App\Entity\Devises;
use App\Entity\Dossier;
use App\Entity\Entetepiece;
use App\Entity\Pays;
use App\Entity\Prospects;
use App\Entity\Reglement;
use App\Entity\Tarifs;
use App\Entity\Tarifvente;
use App\Entity\Unite;
use App\Entity\User;
use App\Entity\Ville;
use Doctrine\Persistence\ManagerRegistry;
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
}
