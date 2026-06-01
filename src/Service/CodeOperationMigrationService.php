<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class CodeOperationMigrationService
{
    private const DEFAULT_CODE_OPERATIONS = [
        [
            'libelle' => 'Vente Standard',
            'sens' => 'Sortie',
            'is_active' => 1,
            'piece_type_devis' => 1,
            'piece_type_commande' => 1,
            'piece_type_b_l' => 1,
            'piece_type_facture' => 1,
            'piece_type_interne' => 0,
        ],
        [
            'libelle' => 'Achat Standard',
            'sens' => 'Entree',
            'is_active' => 1,
            'piece_type_devis' => 1,
            'piece_type_commande' => 1,
            'piece_type_b_l' => 1,
            'piece_type_facture' => 1,
            'piece_type_interne' => 0,
        ],
        [
            'libelle' => 'Transfert Interne Sortie',
            'sens' => 'Sortie',
            'is_active' => 1,
            'piece_type_devis' => 0,
            'piece_type_commande' => 0,
            'piece_type_b_l' => 0,
            'piece_type_facture' => 0,
            'piece_type_interne' => 1,
        ],
        [
            'libelle' => 'Transfert Interne Entree',
            'sens' => 'Entree',
            'is_active' => 1,
            'piece_type_devis' => 0,
            'piece_type_commande' => 0,
            'piece_type_b_l' => 0,
            'piece_type_facture' => 0,
            'piece_type_interne' => 1,
        ],
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        $totalPieces = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM entetepiece');
        $piecesWithoutCodeOperation = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM entetepiece WHERE code_operation_id IS NULL');

        $totalLines = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM lignepiece');
        $linesWithoutSens = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM lignepiece WHERE sens IS NULL');

        $operations = $this->connection->fetchAllAssociative(
            'SELECT id, libelle, sens, is_active FROM code_operation WHERE libelle IN (:labels)',
            ['labels' => array_map(static fn (array $row): string => $row['libelle'], self::DEFAULT_CODE_OPERATIONS)],
            ['labels' => Connection::PARAM_STR_ARRAY]
        );

        $foundLabels = array_map(static fn (array $row): string => (string) ($row['libelle'] ?? ''), $operations);
        $missingDefaultLabels = array_values(array_filter(
            array_map(static fn (array $row): string => $row['libelle'], self::DEFAULT_CODE_OPERATIONS),
            static fn (string $label): bool => !in_array($label, $foundLabels, true)
        ));

        $completion = $totalPieces > 0
            ? round((($totalPieces - $piecesWithoutCodeOperation) / $totalPieces) * 100, 2)
            : 100.0;

        $lineCompletion = $totalLines > 0
            ? round((($totalLines - $linesWithoutSens) / $totalLines) * 100, 2)
            : 100.0;

        return [
            'totalPieces' => $totalPieces,
            'piecesWithoutCodeOperation' => $piecesWithoutCodeOperation,
            'pieceCompletionPercent' => $completion,
            'totalLines' => $totalLines,
            'linesWithoutSens' => $linesWithoutSens,
            'lineCompletionPercent' => $lineCompletion,
            'defaultOperations' => $operations,
            'missingDefaultLabels' => $missingDefaultLabels,
            'isMigrationComplete' => $piecesWithoutCodeOperation === 0
                && $linesWithoutSens === 0
                && $missingDefaultLabels === [],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function runAutomaticMigration(): array
    {
        return $this->runMigrationBatch(null);
    }

    /**
     * @return array<string, int>
     */
    public function runManualMigration(int $limit = 200): array
    {
        $safeLimit = max(1, min($limit, 2000));

        return $this->runMigrationBatch($safeLimit);
    }

    /**
     * Synchronize standard operations without touching existing pieces/lines.
     *
     * @return array<string, int>
     */
    public function synchronizeDefaultOperations(): array
    {
        return $this->connection->transactional(function (Connection $connection): array {
            $updatedOperations = 0;
            $createdOperations = $this->ensureDefaultOperations($connection, $updatedOperations);

            return [
                'createdOperations' => $createdOperations,
                'updatedOperations' => $updatedOperations,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    private function runMigrationBatch(?int $limit): array
    {
        return $this->connection->transactional(function (Connection $connection) use ($limit): array {
            $updatedOperations = 0;
            $createdOperations = $this->ensureDefaultOperations($connection, $updatedOperations);
            $operationIds = $this->resolveDefaultOperationIds($connection);

            $updatedClientPieces = $this->executeAssignment(
                $connection,
                (int) ($operationIds['Vente Standard'] ?? 0),
                "LOWER(COALESCE(ep.typet, '')) IN ('client', 'prospect', 'vat')",
                $limit
            );

            $updatedSupplierPieces = $this->executeAssignment(
                $connection,
                (int) ($operationIds['Achat Standard'] ?? 0),
                "LOWER(COALESCE(ep.typet, '')) = 'fournisseur'",
                $limit
            );

            $updatedInternalPieces = $this->executeAssignment(
                $connection,
                (int) ($operationIds['Transfert Interne Sortie'] ?? 0),
                "LOWER(COALESCE(ep.typet, '')) IN ('interne', 'tiersinterne', 'tiers interne')",
                $limit
            );

            $filledLineSens = $this->fillMissingLineSens($connection, $limit);

            return [
                'createdOperations' => $createdOperations,
                'updatedOperations' => $updatedOperations,
                'updatedClientPieces' => $updatedClientPieces,
                'updatedSupplierPieces' => $updatedSupplierPieces,
                'updatedInternalPieces' => $updatedInternalPieces,
                'filledLineSens' => $filledLineSens,
            ];
        });
    }

    private function ensureDefaultOperations(Connection $connection, ?int &$updatedOperations = null): int
    {
        $created = 0;
        $updated = 0;
        foreach (self::DEFAULT_CODE_OPERATIONS as $operation) {
            $existingId = $connection->fetchOne(
                'SELECT id FROM code_operation WHERE libelle = :libelle LIMIT 1',
                ['libelle' => $operation['libelle']]
            );

            if ($existingId !== false && $existingId !== null) {
                $updated += $connection->executeStatement(
                    'UPDATE code_operation SET
                        sens = :sens,
                        is_active = :is_active,
                        piece_type_devis = :piece_type_devis,
                        piece_type_commande = :piece_type_commande,
                        piece_type_b_l = :piece_type_b_l,
                        piece_type_facture = :piece_type_facture,
                        piece_type_interne = :piece_type_interne,
                        updated_at = NOW()
                    WHERE id = :id',
                    array_merge($operation, ['id' => (int) $existingId])
                );

                continue;
            }

            $inserted = $connection->executeStatement(
                'INSERT INTO code_operation (
                    libelle, sens, is_active,
                    piece_type_devis, piece_type_commande, piece_type_b_l, piece_type_facture, piece_type_interne,
                    created_at, updated_at
                )
                SELECT :libelle, :sens, :is_active, :piece_type_devis, :piece_type_commande, :piece_type_b_l, :piece_type_facture, :piece_type_interne, NOW(), NOW()
                WHERE NOT EXISTS (
                    SELECT 1 FROM code_operation WHERE libelle = :libelle
                )',
                $operation
            );
            $created += $inserted;
        }

        if ($updatedOperations !== null) {
            $updatedOperations = $updated;
        }

        return $created;
    }

    /**
     * @return array<string, int>
     */
    private function resolveDefaultOperationIds(Connection $connection): array
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT id, libelle FROM code_operation WHERE libelle IN (:labels)',
            ['labels' => array_map(static fn (array $row): string => $row['libelle'], self::DEFAULT_CODE_OPERATIONS)],
            ['labels' => Connection::PARAM_STR_ARRAY]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['libelle']] = (int) $row['id'];
        }

        return $result;
    }

    private function executeAssignment(Connection $connection, int $operationId, string $whereClause, ?int $limit): int
    {
        if ($operationId <= 0) {
            return 0;
        }

        $sql = sprintf(
            'UPDATE entetepiece ep SET ep.code_operation_id = :operationId WHERE ep.code_operation_id IS NULL AND %s',
            $whereClause
        );
        if ($limit !== null) {
            $sql .= sprintf(' LIMIT %d', $limit);
        }

        return $connection->executeStatement($sql, ['operationId' => $operationId]);
    }

    private function fillMissingLineSens(Connection $connection, ?int $limit): int
    {
        $sql = 'UPDATE lignepiece lp
            INNER JOIN entetepiece ep ON ep.id = lp.piece_id
            INNER JOIN code_operation co ON co.id = ep.code_operation_id
            SET lp.sens = co.sens
            WHERE lp.sens IS NULL';

        if ($limit !== null) {
            $sql .= sprintf(' LIMIT %d', $limit);
        }

        return $connection->executeStatement($sql);
    }
}
