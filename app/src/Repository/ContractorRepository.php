<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contractor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contractor>
 */
final class ContractorRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contractor::class);
    }

    public function findOneByName(string $name): ?Contractor
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Returns the contractor with the given name, creating and persisting
     * it if it doesn't exist yet. Used as a safety net for system
     * contractors (see App\Constant\Contractors) that are expected to
     * always exist: if the seeding migration was never run, or the row was
     * deleted by mistake, this self-heals instead of failing the request.
     */
    public function findOrCreateByName(string $name): Contractor
    {
        $contractor = $this->findOneByName($name);
        if (null !== $contractor) {
            return $contractor;
        }

        $contractor = new Contractor($name);
        $this->save($contractor, flush: true);

        return $contractor;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        $trimmedQuery = trim($query);
        if ('' === $trimmedQuery) {
            return [];
        }

        /** @var array<int, array{id: int, name: string}> $result */
        $result = $this->createQueryBuilder('c')
            ->select('c.id, c.name')
            ->where('LOWER(c.name) LIKE LOWER(:q)')
            ->setParameter('q', '%'.$trimmedQuery.'%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return $result;
    }
}
