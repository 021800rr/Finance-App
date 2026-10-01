<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GoldAsset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoldAsset>
 */
final class GoldAssetRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoldAsset::class);
    }

    /**
     * @return GoldAsset[]
     */
    public function findAllOrdered(): array
    {
        /** @var GoldAsset[] $result */
        $result = $this->createQueryBuilder('ga')
            ->join('ga.form', 'gf')
            ->addSelect('gf')
            ->orderBy('ga.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
