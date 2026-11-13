<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GoldPrice;
use App\Service\Paginator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoldPrice>
 *
 * @phpstan-type ChartPriceRow array{
 *     id: int|string,
 *     gold_asset_id: int|string,
 *     asset_description: string,
 *     currency_code: string,
 *     price_date: string,
 *     price_pln_minor_units: int|string,
 * }
 */
final class GoldPriceRepository extends ServiceEntityRepository
{
    /** @use FindsByIdOrNullTrait<GoldPrice> */
    use FindsByIdOrNullTrait;
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoldPrice::class);
    }

    /**
     * Uses the latest price date per asset, breaking ties by the highest ID.
     *
     * @throws Exception
     */
    public function fetchSummary(): int
    {
        $latestPrices = LatestQuoteQuery::build(
            table: 'gold_price',
            groupColumns: ['gold_asset_id'],
            dateColumn: 'price_date'
        );

        /** @var string $result */
        $result = $this->getEntityManager()->getConnection()->fetchOne(
            <<<SQL
            SELECT COALESCE(SUM(latest.price_pln_minor_units), 0)
            FROM ({$latestPrices}) latest
            SQL
        );

        return (int) $result;
    }

    /**
     * Price entries ordered by date, then ID, newest first. Shared by the web list and the API.
     * Eagerly load the asset because the web list displays its description.
     *
     * @return GoldPrice[]
     */
    public function findDataForList(int $limit = Paginator::DEFAULT_PER_PAGE): array
    {
        /** @var GoldPrice[] $result */
        $result = $this->createQueryBuilder('gp')
            ->addSelect('ga')
            ->join('gp.goldAsset', 'ga')
            ->orderBy('gp.priceDate', 'DESC')
            ->addOrderBy('gp.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Historical prices as scalar rows, shared by the API and Twig chart.
     * Selects the highest ID per asset/day. Dates are YYYY-MM-DD and prices remain in minor units.
     *
     * @return list<ChartPriceRow>
     *
     * @throws Exception
     */
    public function findDataForChart(): array
    {
        $dailyPrices = LatestQuoteQuery::build(
            table: 'gold_price',
            groupColumns: ['gold_asset_id'],
            dateColumn: 'price_date',
            perDay: true
        );

        /** @var list<ChartPriceRow> $prices */
        $prices = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<SQL
            SELECT
                latest.id,
                latest.gold_asset_id,
                ga.description AS asset_description,
                latest.currency_code,
                latest.price_date,
                latest.price_pln_minor_units
            FROM ({$dailyPrices}) latest
            JOIN gold_asset ga ON ga.id = latest.gold_asset_id
            ORDER BY latest.price_date, latest.id
            SQL
        );

        return $prices;
    }
}
