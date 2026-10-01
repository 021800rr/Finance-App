<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Household;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Household>
 */
final class HouseholdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Household::class);
    }

    public function findOwnFundsHouseholdName(): string
    {
        try {
            /** @var string $result */
            $result = $this->createQueryBuilder('h')
                ->select('h.name')
                ->where('h.isOwnFunds = true')
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NoResultException|NonUniqueResultException $e) {
            throw new \RuntimeException('Expected exactly one own-funds household.', previous: $e);
        }

        return $result;
    }
}
