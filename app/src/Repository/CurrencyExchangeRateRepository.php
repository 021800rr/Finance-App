<?php

declare(strict_types=1);

namespace App\Repository;

use App\Constant\Currencies;
use App\Entity\CurrencyExchangeRate;
use App\Service\Paginator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CurrencyExchangeRate>
 *
 * @phpstan-type ChartRateRow array{
 *     id: int|string,
 *     bank_id: int|string,
 *     bank_name: string,
 *     currency_code: string,
 *     rate_date: string,
 *     rate_to_pln_minor_units: int|string,
 * }
 */
final class CurrencyExchangeRateRepository extends ServiceEntityRepository
{
    /** @use FindsByIdOrNullTrait<CurrencyExchangeRate> */
    use FindsByIdOrNullTrait;
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
    public function fetchSummary(): array
    {
        $latestRates = LatestQuoteQuery::build('currency_exchange_rate', ['bank_id', 'currency_code'], 'rate_date');

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<SQL
            SELECT b.name AS bank, latest.currency_code AS currency, latest.rate_to_pln_minor_units
            FROM ({$latestRates}) latest
            JOIN bank b ON b.id = latest.bank_id
            ORDER BY b.name, latest.currency_code
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
     * Rate entries ordered by date, then ID, newest first. Shared by the web list and the API.
     * Eagerly load bank and currency because the web list displays their names/codes.
     *
     * @return CurrencyExchangeRate[]
     */
    public function findDataForList(int $limit = Paginator::DEFAULT_PER_PAGE): array
    {
        /** @var CurrencyExchangeRate[] $result */
        $result = $this->createQueryBuilder('r')
            ->addSelect('b', 'c')
            ->join('r.bank', 'b')
            ->join('r.currency', 'c')
            ->orderBy('r.rateDate', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Historical rates for the tracked currency as scalar rows, shared by the API and Twig chart.
     * Selects the highest ID per bank/currency/day for both callers.
     * Dates are YYYY-MM-DD and rates remain in minor units for callers to format.
     *
     * @return list<ChartRateRow>
     *
     * @throws Exception
     */
    public function findDataForChart(): array
    {
        $dailyRates = LatestQuoteQuery::build(
            'currency_exchange_rate',
            ['bank_id', 'currency_code'],
            'rate_date',
            perDay: true,
        );

        /** @var list<ChartRateRow> $rates */
        $rates = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<SQL
            SELECT r.id, r.bank_id, b.name AS bank_name, r.currency_code, r.rate_date, r.rate_to_pln_minor_units
            FROM ({$dailyRates}) r
            JOIN bank b ON b.id = r.bank_id
            WHERE r.currency_code = :currency
            ORDER BY r.rate_date, r.id
            SQL,
            ['currency' => Currencies::TRACKED_FOREIGN_CURRENCY_CODE],
        );

        return $rates;
    }
}
