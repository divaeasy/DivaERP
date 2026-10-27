<?php

namespace App\Repository;

use App\Entity\CodeOperation;
use App\Enum\SensEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CodeOperation>
 */
class CodeOperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CodeOperation::class);
    }

    /**
     * @return array<int, CodeOperation>
     */
    public function findActiveForScope(string $scope, string $normalizedPieceType): array
    {
        // Load all active operations once (avoids DQL issues with pieceTypeBL column naming)
        $allActive = $this->createQueryBuilder('co')
            ->andWhere('co.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('co.libelle', 'ASC')
            ->getQuery()
            ->getResult();

        if ($scope === 'interne') {
            return array_filter($allActive, static fn (CodeOperation $co): bool =>
                $co->isPieceTypeInterne() && $co->getLibelle() !== 'Operation Interne'
            );
        }

        // Determine required sens based on scope
        $requiredSens = $scope === 'client' ? SensEnum::CREDIT : SensEnum::DEBIT;

        // Map piece type to method name
        $methodName = match ($normalizedPieceType) {
            'devis' => 'isPieceTypeDevis',
            'commande' => 'isPieceTypeCommande',
            'bl' => 'isPieceTypeBL',
            default => 'isPieceTypeFacture',
        };

        // Filter in PHP to avoid DQL naming ambiguities (pieceTypeBL → piece_type_bl vs piece_type_b_l)
        return array_filter(
            $allActive,
            static fn (CodeOperation $co): bool =>
                $co->getSens() === $requiredSens && $co->$methodName()
        );
    }

    public function countActiveOperations(): int
    {
        return (int) $this->createQueryBuilder('co')
            ->select('COUNT(co.id)')
            ->andWhere('co.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
