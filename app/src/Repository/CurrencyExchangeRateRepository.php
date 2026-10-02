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
     * Most recent rates, newest-added first (ordered by id DESC, so the most recently added rate
     * wins regardless of rate date). Shared by the web list (default limit) and the API's
     * GetCollection (fixed limit, no pagination) — both read bank.name/currency.code, not just
     * identifiers, so bank/currency are eagerly selected (addSelect) to avoid N+1 lazy-loading one
     * query per distinct bank. Contrast with findAllOrderedForChart(), which only reads identifiers
     * and therefore needs no join/select at all.
     *
     * @return CurrencyExchangeRate[]
     */
    public function findLatestOrdered(int $limit = 20): array
    {
        /** @var CurrencyExchangeRate[] $result */
        $result = $this->createQueryBuilder('r')
            ->addSelect('b', 'c')
            ->join('r.bank', 'b')
            ->join('r.currency', 'c')
            ->orderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Full historical rates for the tracked foreign currency, for the API's chart/history endpoint.
     * Unlike findHistoricalRatesForChart(), returns raw entities (no grouping/dedup) so the API stays
     * a normalized, flat resource; grouping by bank/date is left to the consumer.
     *
     * Filtered to Currencies::TRACKED_FOREIGN_CURRENCY_CODE via IDENTITY(r.currency) on the owning
     * side's FK column — no join needed, since bank_id/currency_code already live on this table and
     * fromEntity() only ever reads identifiers (see CurrencyExchangeRateResource).
     *
     * @return CurrencyExchangeRate[]
     */
    public function findAllOrderedForChart(): array
    {
        /** @var CurrencyExchangeRate[] $result */
        $result = $this->createQueryBuilder('r')
            ->where('IDENTITY(r.currency) = :currency')
            ->setParameter('currency', Currencies::TRACKED_FOREIGN_CURRENCY_CODE)
            ->orderBy('r.rateDate', 'ASC')
            ->addOrderBy('r.id', 'ASC')
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
            ->where('IDENTITY(r.currency) = :currency')
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
