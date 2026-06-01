<?php

namespace App\Service;

use App\Entity\Entetepiece;
use App\Entity\Lignepiece;
use App\Enum\SensEnum;

class AccountingEntryGeneratorService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function generateForPiece(Entetepiece $piece): array
    {
        $operation = $piece->getCodeOperation();
        $operationLabel = trim((string) ($operation?->getLibelle() ?? 'Operation'));
        $operationSens = $operation?->getSens();
        $lines = $piece->getLignepieces()->toArray();
        $isInternalPiece = $this->isInternalPiece($piece);

        if ($isInternalPiece) {
            return $this->buildInternalEntries($piece, $operationLabel, $lines);
        }

        $entries = [];
        foreach ($lines as $line) {
            if (!$line instanceof Lignepiece) {
                continue;
            }

            $amount = $this->resolveLineAmount($line);
            if ($amount <= 0) {
                continue;
            }

            $sens = $line->getSens() ?? $operationSens ?? SensEnum::CREDIT;
            $debit = $sens === SensEnum::DEBIT ? $amount : 0.0;
            $credit = $sens === SensEnum::CREDIT ? $amount : 0.0;
            $lineLabel = trim((string) ($line->getDesignation() ?? 'Ligne'));

            $entries[] = [
                'pieceId' => $piece->getId(),
                'pieceRef' => (string) ($piece->getPieceref() ?? ''),
                'pieceType' => (string) ($piece->getType() ?? ''),
                'codeOperation' => $operationLabel,
                'sens' => $sens->label(),
                'libelle' => sprintf('%s - %s - %s', $this->resolvePieceLabel($piece), $operationLabel, $lineLabel),
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'tierSource' => (string) $piece->getTierName(),
                'tierDestination' => '',
                'lineId' => $line->getId(),
            ];
        }

        if ($entries !== []) {
            return $entries;
        }

        $fallbackAmount = max(0.0, round((float) ($piece->getMontant() ?? 0), 2));
        if ($fallbackAmount <= 0) {
            return [];
        }

        $fallbackSens = $operationSens ?? SensEnum::CREDIT;

        return [[
            'pieceId' => $piece->getId(),
            'pieceRef' => (string) ($piece->getPieceref() ?? ''),
            'pieceType' => (string) ($piece->getType() ?? ''),
            'codeOperation' => $operationLabel,
            'sens' => $fallbackSens->label(),
            'libelle' => sprintf('%s - %s', $this->resolvePieceLabel($piece), $operationLabel),
            'debit' => $fallbackSens === SensEnum::DEBIT ? $fallbackAmount : 0.0,
            'credit' => $fallbackSens === SensEnum::CREDIT ? $fallbackAmount : 0.0,
            'tierSource' => (string) $piece->getTierName(),
            'tierDestination' => '',
            'lineId' => null,
        ]];
    }

    /**
     * @param array<int, Lignepiece> $lines
     * @return array<int, array<string, mixed>>
     */
    private function buildInternalEntries(Entetepiece $piece, string $operationLabel, array $lines): array
    {
        $total = 0.0;
        foreach ($lines as $line) {
            if (!$line instanceof Lignepiece) {
                continue;
            }
            $total += $this->resolveLineAmount($line);
        }

        if ($total <= 0) {
            $total = max(0.0, round((float) ($piece->getMontant() ?? 0), 2));
        }

        if ($total <= 0) {
            return [];
        }

        $source = (string) $piece->getTierName();
        $destination = trim((string) ($piece->getTierDestination()?->getNom() ?? ''));
        if ($destination === '') {
            $destination = 'Destination interne';
        }

        $pieceLabel = $this->resolvePieceLabel($piece);

        return [
            [
                'pieceId' => $piece->getId(),
                'pieceRef' => (string) ($piece->getPieceref() ?? ''),
                'pieceType' => (string) ($piece->getType() ?? ''),
                'codeOperation' => $operationLabel,
                'sens' => SensEnum::DEBIT->label(),
                'libelle' => sprintf('%s - %s - Debit destination', $pieceLabel, $operationLabel),
                'debit' => round($total, 2),
                'credit' => 0.0,
                'tierSource' => $source,
                'tierDestination' => $destination,
                'lineId' => null,
            ],
            [
                'pieceId' => $piece->getId(),
                'pieceRef' => (string) ($piece->getPieceref() ?? ''),
                'pieceType' => (string) ($piece->getType() ?? ''),
                'codeOperation' => $operationLabel,
                'sens' => SensEnum::CREDIT->label(),
                'libelle' => sprintf('%s - %s - Credit source', $pieceLabel, $operationLabel),
                'debit' => 0.0,
                'credit' => round($total, 2),
                'tierSource' => $source,
                'tierDestination' => $destination,
                'lineId' => null,
            ],
        ];
    }

    private function isInternalPiece(Entetepiece $piece): bool
    {
        $normalized = $this->normalizeToken($piece->getTypet());

        return in_array($normalized, ['interne', 'tiersinterne', 'tierinterne'], true);
    }

    private function resolveLineAmount(Lignepiece $line): float
    {
        $amount = round((float) ($line->getMontant() ?? 0), 2);
        if ($amount > 0) {
            return $amount;
        }

        $quantity = (float) ($line->getQte() ?? 0);
        $unitPrice = (float) ($line->getPub() ?? 0);

        return round(max(0.0, $quantity * $unitPrice), 2);
    }

    private function resolvePieceLabel(Entetepiece $piece): string
    {
        $type = trim((string) ($piece->getType() ?? 'Piece'));
        $number = $piece->getPieceno() !== null ? '#' . (string) $piece->getPieceno() : '#' . (string) ($piece->getId() ?? '');

        return trim($type . ' ' . $number);
    }

    private function normalizeToken(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value), 'UTF-8');
        $normalized = strtr($normalized, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ç' => 'c',
            'œ' => 'oe',
            'æ' => 'ae',
        ]);

        return (string) preg_replace('/[^a-z0-9]/', '', $normalized);
    }
}
