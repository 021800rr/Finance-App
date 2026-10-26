<?php

declare(strict_types=1);

namespace App\Repository;

use App\Constant\Currencies;
use App\Entity\GoldPrice;
use App\Service\Paginator;
use App\ValueObject\Money;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoldPrice>
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
        $latestPrices = LatestQuoteQuery::build('gold_price', ['gold_asset_id'], 'price_date');

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
     * @return list<array{assetId: int, label: string, prices: array<string, float>}>
     *
     * @throws Exception
     */
    public function findDataForChart(): array
    {
        $dailyPrices = LatestQuoteQuery::build('gold_price', ['gold_asset_id'], 'price_date', perDay: true);

        /**
         * @var list<array{asset_id: int, label: string, price_date: string, price_pln_minor_units: int|string}> $prices
         */
        $prices = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<SQL
            SELECT latest.gold_asset_id AS asset_id, ga.description AS label,
                   latest.price_date, latest.price_pln_minor_units
            FROM ({$dailyPrices}) latest
            JOIN gold_asset ga ON ga.id = latest.gold_asset_id
            ORDER BY latest.price_date, latest.id
            SQL
        );

        $data = [];
        foreach ($prices as $price) {
            $assetId = (int) $price['asset_id'];
            $data[$assetId] ??= ['assetId' => $assetId, 'label' => $price['label'], 'prices' => []];
            $data[$assetId]['prices'][$price['price_date']] = (float) Money::fromMinorUnits(
                (int) $price['price_pln_minor_units'],
                Currencies::SETTLEMENT_CURRENCY_CODE,
                Currencies::MINOR_UNIT_SCALE
            )->toDecimalString();
        }

        return array_values($data);
    }
}
