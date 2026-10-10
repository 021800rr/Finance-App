<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\ValueObject\Money;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * @return AccountOperation[]
     */
    public function findByAccountOrdered(Account $account): array
    {
        /** @var AccountOperation[] $result */
        $result = $this->createQueryBuilder('o')
            ->join('o.contractor', 'c')
            ->addSelect('c')
            ->where('o.account = :account')
            ->setParameter('account', $account)
            ->orderBy('o.operationDate', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
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
    public function findAllOrderedPaged(int $limit, int $offset): array
    {
        /** @var AccountOperation[] $result */
        $result = $this->createQueryBuilder('o')
            ->join('o.contractor', 'c')
            ->addSelect('c')
            ->orderBy('o.operationDate', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return AccountOperation[]
     */
    public function searchByQueryPaged(string $query, int $limit, int $offset, ?Account $account = null): array
    {
        /** @var AccountOperation[] $result */
        $result = $this->buildSearchQuery($query, $account)
            ->addSelect('c', 'a')
            ->orderBy('o.operationDate', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countByQuery(string $query, ?Account $account = null): int
    {
        return (int) $this->buildSearchQuery($query, $account)
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
            ->setParameter('date', $date)
            ->setParameter('amount', $amount)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $count) > 0;
    }

    /**
     * Matches the text in the description or the contractor name (case-insensitive substring) or, when the
     * text parses as an amount, the exact amount. LIKE wildcards in the input are escaped, so "%", "_" and
     * "\\" only ever match themselves. Optionally restricted to one account.
     */
    private function buildSearchQuery(string $query, ?Account $account = null): QueryBuilder
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

        if (null !== $account) {
            $qb->andWhere('o.account = :account')
                ->setParameter('account', $account);
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
