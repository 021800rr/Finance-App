<?php

declare(strict_types=1);

namespace App\Repository;

use App\Constant\Currencies;
use App\Entity\Currency;
use App\Util\EntityOrThrow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Currency>
 */
final class CurrencyRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Currency::class);
    }

    /**
     * Currencies an exchange rate may be added for: every currency except PLN, which rates are quoted in.
     *
     * @return list<Currency>
     */
    public function findForeignOrderedByCode(): array
    {
        /** @var list<Currency> $currencies */
        $currencies = $this->createQueryBuilder('c')
            ->where('c.code != :settlementCurrency')
            ->setParameter('settlementCurrency', Currencies::SETTLEMENT_CURRENCY_CODE)
            ->orderBy('c.code', 'ASC')
            ->getQuery()
            ->getResult();

        return $currencies;
    }

    public function findPlnOrThrow(): Currency
    {
        return EntityOrThrow::require(
            $this->find(Currencies::SETTLEMENT_CURRENCY_CODE),
            'PLN currency not found in database.'
        );
    }
}
