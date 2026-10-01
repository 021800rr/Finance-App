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

    public function findPlnOrThrow(): Currency
    {
        return EntityOrThrow::require(
            $this->find(Currencies::SETTLEMENT_CURRENCY_CODE),
            'PLN currency not found in database.'
        );
    }
}
