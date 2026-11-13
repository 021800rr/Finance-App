<?php

declare(strict_types=1);

namespace App\Repository;

use App\Constant\Currencies;
use App\Entity\Bank;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * Banks an exchange rate may be added for: those with at least one visible foreign-currency (non-PLN) account.
     *
     * @return list<Bank>
     */
    public function findWithVisibleForeignCurrencyAccount(): array
    {
        /** @var list<Bank> $banks */
        $banks = $this->createWithVisibleForeignCurrencyAccountQueryBuilder()
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $banks;
    }

    public function hasVisibleForeignCurrencyAccount(Bank $bank): bool
    {
        return null !== $this->createWithVisibleForeignCurrencyAccountQueryBuilder()
            ->andWhere('b = :bank')
            ->setParameter('bank', $bank)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function createWithVisibleForeignCurrencyAccountQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('b')
            ->join('b.accounts', 'a')
            ->join('a.currency', 'c')
            ->where('a.hidden = false')
            ->andWhere('c.code != :settlementCurrency')
            ->setParameter('settlementCurrency', Currencies::SETTLEMENT_CURRENCY_CODE)
            ->distinct();
    }
}
