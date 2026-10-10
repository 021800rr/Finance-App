<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GoldForm;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GoldForm> */
final class GoldFormRepository extends ServiceEntityRepository
{
    /** @use FindsByIdOrNullTrait<GoldForm> */
    use FindsByIdOrNullTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoldForm::class);
    }
}
