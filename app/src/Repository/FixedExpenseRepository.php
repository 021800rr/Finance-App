<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FixedExpense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FixedExpense>
 */
final class FixedExpenseRepository extends ServiceEntityRepository
{
    /** @use FindsByIdOrNullTrait<FixedExpense> */
    use FindsByIdOrNullTrait;
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FixedExpense::class);
    }

    /**
     * @return FixedExpense[]
     */
    public function findAllOrdered(): array
    {
        /** @var FixedExpense[] $result */
        $result = $this->createQueryBuilder('f')
            ->join('f.contractor', 'c')
            ->addSelect('c')
            ->orderBy('f.day', 'ASC')
            ->addOrderBy('f.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
