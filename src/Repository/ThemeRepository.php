<?php

namespace App\Repository;

use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Theme>
 */
class ThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Theme::class);
    }

    public function findDefaultTheme(): ?Theme
    {
        $default = $this->findOneBy(['code' => 'indigo']);
        if ($default instanceof Theme) {
            return $default;
        }

        return $this->createQueryBuilder('t')
            ->orderBy('t.isSystem', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

