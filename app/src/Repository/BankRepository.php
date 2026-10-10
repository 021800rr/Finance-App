<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Bank;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Bank> */
final class BankRepository extends ServiceEntityRepository
{
    /** @use FindsByIdOrNullTrait<Bank> */
    use FindsByIdOrNullTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bank::class);
    }
}
