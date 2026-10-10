<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AccountType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AccountType> */
final class AccountTypeRepository extends ServiceEntityRepository
{
    /** @use FindsByIdOrNullTrait<AccountType> */
    use FindsByIdOrNullTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountType::class);
    }
}
