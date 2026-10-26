<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\ValueObject\Money;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountOperation>
 */
final class AccountOperationRepository extends ServiceEntityRepository
{
    /** @use FindsByIdOrNullTrait<AccountOperation> */
    use FindsByIdOrNullTrait;
    use SaveRemoveTrait;

    private const string NO_CURRENCY_CODE = 'XXX';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountOperation::class);
    }

    /**
     * Sums the account's operations in a single query: all of them and those dated up to and including $today.
     * The amounts are in the account's currency (an operation cannot be in any other, see AccountOperation).
     *
     * @return array{total: int, upToToday: int}
     */
    public function sumBalancesForAccount(Account $account, \DateTimeImmutable $today): array
    {
        /** @var array{total: string|null, upToToday: string|null} $row */
        $row = $this->createQueryBuilder('o')
            ->select('SUM(o.amount) AS total')
            ->addSelect('SUM(CASE WHEN o.operationDate <= :today THEN o.amount ELSE 0 END) AS upToToday')
            ->where('o.account = :account')
            ->setParameter('account', $account)
            ->setParameter('today', $today, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $row['total'],
            'upToToday' => (int) $row['upToToday'],
        ];
    }

    /**
     * @return AccountOperation[]
     */
    public function findByAccountOrderedPaged(Account $account, int $limit, int $offset): array
    {
        /** @var AccountOperation[] $result */
        $result = $this->createQueryBuilder('o')
            ->join('o.contractor', 'c')
            ->addSelect('c')
            ->where('o.account = :account')
            ->setParameter('account', $account)
            ->orderBy('o.operationDate', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countByAccount(Account $account): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.account = :account')
            ->setParameter('account', $account)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return AccountOperation[]
     */
    public function searchByQueryPaged(string $query, int $limit, int $offset): array
    {
        /** @var AccountOperation[] $result */
        $result = $this->buildSearchQuery($query)
            ->addSelect('c', 'a')
            ->orderBy('o.operationDate', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countByQuery(string $query): int
    {
        return (int) $this->buildSearchQuery($query)
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOtherSide(AccountOperation $operation): ?AccountOperation
    {
        if (!$operation->getTransactionUuid()) {
            return null;
        }

        /** @var ?AccountOperation $result */
        $result = $this->createQueryBuilder('o')
            ->where('o.transactionUuid = :uuid')
            ->andWhere('o.id != :id')
            ->setParameter('uuid', $operation->getTransactionUuid())
            ->setParameter('id', $operation->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function existsForContractorAndDate(
        Account $account,
        Contractor $contractor,
        \DateTimeImmutable $date,
        int $amount,
    ): bool {
        /** @var numeric-string $count */
        $count = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.account = :account')
            ->andWhere('o.contractor = :contractor')
            ->andWhere('o.operationDate = :date')
            ->andWhere('o.amount = :amount')
            ->setParameter('account', $account)
            ->setParameter('contractor', $contractor)
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->setParameter('amount', $amount)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $count) > 0;
    }

    /**
     * Matches the text in the description or the contractor name (case-insensitive substring) or, when the
     * text parses as an amount, the exact amount. LIKE wildcards in the input are escaped, so "%", "_" and
     * "\\" only ever match themselves.
     */
    private function buildSearchQuery(string $query): QueryBuilder
    {
        $query = trim($query);

        $qb = $this->createQueryBuilder('o')
            ->join('o.contractor', 'c')
            ->join('o.account', 'a')
            ->where('LOWER(o.description) LIKE LOWER(:q) OR LOWER(c.name) LIKE LOWER(:q)')
            ->setParameter('q', '%'.addcslashes($query, '\\%_').'%');

        $exactAmount = $this->tryParseAmountQuery($query);
        if (null !== $exactAmount) {
            $qb->orWhere('o.amount = :exact_q')
                ->setParameter('exact_q', $exactAmount);
        }

        return $qb;
    }

    private function tryParseAmountQuery(string $query): ?int
    {
        $normalized = str_replace(',', '.', trim($query));

        try {
            return Money::fromDecimalString($normalized, self::NO_CURRENCY_CODE)->getMinorUnits();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
