<?php

declare(strict_types=1);

namespace App\Repository;

use App\Constant\Currencies;
use App\Entity\CurrencyExchangeRate;
use App\ValueObject\Money;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CurrencyExchangeRate>
 */
final class CurrencyExchangeRateRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CurrencyExchangeRate::class);
    }

    /**
     * @return array<string, array<string, int>>
     *
     * @throws Exception
     */
    public function fetchLatestRatesToPlnByBank(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<SQL
            SELECT b.name AS bank, c.code AS currency, r.rate_to_pln_minor_units
            FROM currency_exchange_rate r
            JOIN currency c ON c.code = r.currency_code
            JOIN bank b ON b.id = r.bank_id
            WHERE r.id = (
                SELECT r2.id
                FROM currency_exchange_rate r2
                WHERE r2.currency_code = r.currency_code
                  AND r2.bank_id = r.bank_id
                ORDER BY r2.id DESC
                LIMIT 1
            )
            SQL
        );

        $rates = [];

        foreach ($rows as $row) {
            /** @var string $bank */
            $bank = $row['bank'];
            /** @var string $currency */
            $currency = $row['currency'];
            /** @var int $rate */
            $rate = $row['rate_to_pln_minor_units'];
            $rates[$bank][$currency] = $rate;
        }

        return $rates;
    }

    /**
     * @return CurrencyExchangeRate[]
     */
    public function findAllOrderedForList(): array
    {
        /** @var CurrencyExchangeRate[] $result */
        $result = $this->createQueryBuilder('r')
            ->join('r.bank', 'b')
            ->join('r.currency', 'c')
            ->orderBy('r.id', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Latest rates for the API's GetCollection (fixed limit, no pagination).
     * Ordered by id DESC: the most recently added rate wins, regardless of rate date.
     *
     * @return CurrencyExchangeRate[]
     */
    public function findLatestOrderedForApi(int $limit = 10): array
    {
        /** @var CurrencyExchangeRate[] $result */
        $result = $this->createQueryBuilder('r')
            ->join('r.bank', 'b')
            ->join('r.currency', 'c')
            ->orderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return array<string, array<string, float>> Bank name => [date => rate]
     */
    public function findHistoricalRatesForChart(): array
    {
        /** @var list<array{bankName: string, rateDate: \DateTimeImmutable, rateToPln: string}> $rates */
        $rates = $this->createQueryBuilder('r')
            ->select('b.name as bankName, r.rateDate, r.rateToPln')
            ->join('r.bank', 'b')
            ->join('r.currency', 'c', 'WITH', 'c.code = :currency')
            ->setParameter('currency', Currencies::TRACKED_FOREIGN_CURRENCY_CODE)
            ->orderBy('r.rateDate', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();

        // When several rates exist for the same day, later iterations (higher id) overwrite
        // earlier ones, so the most recently added rate wins per bank/day.
        $data = [];
        foreach ($rates as $rate) {
            $bankName = $rate['bankName'];
            $date = $rate['rateDate']->format('Y-m-d');
            $data[$bankName][$date] = (float) Money::fromMinorUnits(
                (int) $rate['rateToPln'],
                Currencies::SETTLEMENT_CURRENCY_CODE,
                Currencies::EXCHANGE_RATE_SCALE
            )->toDecimalString();
        }

        return $data;
    }
}
