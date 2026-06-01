<?php

namespace App\Service;

use App\Entity\CodeOperation;
use App\Entity\Entetepiece;
use App\Enum\SensEnum;
use App\Repository\CodeOperationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Throwable;

class CodeOperationService
{
    private bool $didAutoSyncDefaults = false;

    public function __construct(
        private readonly CodeOperationRepository $repository,
        private readonly Security $security,
        private readonly CodeOperationMigrationService $migrationService,
    ) {
    }

    public function canManageCodeOperations(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted('ROLE_COMPTABLE');
    }

    public function resolvePieceScopeFromTierType(?string $tierType): ?string
    {
        return match ($this->normalizeToken($tierType)) {
            'client', 'prospect', 'vat' => 'client',
            'fournisseur' => 'fournisseur',
            'interne', 'tiersinterne', 'tierinterne' => 'interne',
            default => null,
        };
    }

    /**
     * @return array<int, CodeOperation>
     */
    public function getActiveForPiece(?string $tierType, ?string $pieceType): array
    {
        $scope = $this->resolvePieceScopeFromTierType($tierType);
        if ($scope === null) {
            return [];
        }

        $normalizedPieceType = $this->normalizePieceType($pieceType);
        $operations = $this->repository->findActiveForScope($scope, $normalizedPieceType);

        // Auto-heal legacy standard-code configuration once per request if needed.
        if ($operations === [] && !$this->didAutoSyncDefaults) {
            $this->didAutoSyncDefaults = true;
            try {
                $this->migrationService->synchronizeDefaultOperations();
                $operations = $this->repository->findActiveForScope($scope, $normalizedPieceType);
            } catch (Throwable) {
                // If sync fails, keep existing behavior and let caller handle empty choices.
            }
        }

        return $operations;
    }

    public function resolveCodeOperationForTierType(?string $tierType, ?string $pieceType = null): ?CodeOperation
    {
        $operations = $this->getActiveForPiece($tierType, $pieceType);
        if ($operations === []) {
            return null;
        }

        if ($this->resolvePieceScopeFromTierType($tierType) === 'interne') {
            return null;
        }

        return count($operations) === 1 ? $operations[0] : null;
    }

    public function resolveCodeOperationByIdForTierType(int $id, ?string $tierType, ?string $pieceType = null): ?CodeOperation
    {
        $operation = $this->repository->find($id);
        if (!$operation instanceof CodeOperation || !$operation->isActive()) {
            return null;
        }

        return $this->isOperationAllowedForTierType($operation, $tierType, $pieceType) ? $operation : null;
    }

    public function isOperationAllowedForTierType(CodeOperation $operation, ?string $tierType, ?string $pieceType = null): bool
    {
        $scope = $this->resolvePieceScopeFromTierType($tierType);
        if ($scope === null) {
            return false;
        }

        if ($scope === 'interne') {
            return $operation->isPieceTypeInterne();
        }

        if (!$this->operationSupportsPieceType($operation, $this->normalizePieceType($pieceType))) {
            return false;
        }

        return $scope === 'client'
            ? $operation->getSens() === SensEnum::CREDIT
            : $operation->getSens() === SensEnum::DEBIT;
    }

    public function resolveLineSensFromPiece(?Entetepiece $piece): ?SensEnum
    {
        return $piece?->getCodeOperation()?->getSens();
    }

    public function needsManualCodeOperationChoice(?string $tierType, ?string $pieceType): bool
    {
        $scope = $this->resolvePieceScopeFromTierType($tierType);
        if ($scope === null) {
            return true;
        }

        if ($scope === 'interne') {
            return true;
        }

        return count($this->getActiveForPiece($tierType, $pieceType)) !== 1;
    }

    private function normalizePieceType(?string $pieceType): string
    {
        return match ($this->normalizeToken($pieceType)) {
            'devis' => 'devis',
            'commande' => 'commande',
            'bl' => 'bl',
            'facture' => 'facture',
            default => 'facture',
        };
    }

    private function operationSupportsPieceType(CodeOperation $operation, string $normalizedPieceType): bool
    {
        return match ($normalizedPieceType) {
            'devis' => $operation->isPieceTypeDevis(),
            'commande' => $operation->isPieceTypeCommande(),
            'bl' => $operation->isPieceTypeBL(),
            default => $operation->isPieceTypeFacture(),
        };
    }

    private function normalizeToken(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($ascii) && $ascii !== '') {
            $normalized = strtolower($ascii);
        }

        return (string) preg_replace('/[^a-z0-9]/', '', $normalized);
    }
}
