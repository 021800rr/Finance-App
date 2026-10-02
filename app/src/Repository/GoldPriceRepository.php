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
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoldPrice::class);
    }

    /**
     * @throws Exception
     */
    public function fetchLatestTotalPricePln(): int
    {
        /** @var string $result */
        $result = $this->getEntityManager()->getConnection()->fetchOne(
            <<<SQL
            SELECT COALESCE(SUM(gp.price_pln_minor_units), 0)
            FROM gold_price gp
            INNER JOIN (
                SELECT gold_asset_id, MAX(price_date) AS max_date
                FROM gold_price
                GROUP BY gold_asset_id
            ) latest
                ON latest.gold_asset_id = gp.gold_asset_id
            AND latest.max_date = gp.price_date
            SQL
        );

        return (int) $result;
    }

    /**
     * Most recent prices, newest first. Shared verbatim by the web list and the API's
     * GetCollection — both are the same "page 1" view, no page 2 ever requested, so neither
     * bothers with pagination. The web list reads goldAsset.description (not just the
     * identifier), so goldAsset is eagerly selected (addSelect) to avoid N+1 lazy-loading one
     * query per distinct asset. The join to goldAsset.form is kept (needed for the ORDER BY
     * tie-breaker) but its columns are not selected, since nothing reads form fields here —
     * contrast with findHistoricalPricesForChart(), which does read the form name.
     *
     * @return GoldPrice[]
     */
    public function findLatestOrdered(int $limit = Paginator::DEFAULT_PER_PAGE): array
    {
        /** @var GoldPrice[] $result */
        $result = $this->createQueryBuilder('gp')
            ->addSelect('ga')
            ->join('gp.goldAsset', 'ga')
            ->join('ga.form', 'gf')
            ->orderBy('gp.priceDate', 'DESC')
            ->addOrderBy('gf.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return array<string, array<string, float>> Gold form name => [date => price]
     */
    public function findHistoricalPricesForChart(): array
    {
        /**
         * @var list<array{formName: string, priceDate: \DateTimeImmutable, pricePln: string}> $prices
         */
        $prices = $this->createQueryBuilder('gp')
            ->select('gf.name as formName, gp.priceDate, gp.pricePln')
            ->join('gp.goldAsset', 'ga')
            ->join('ga.form', 'gf')
            ->orderBy('gp.priceDate', 'ASC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($prices as $price) {
            $formName = $price['formName'];
            $date = $price['priceDate']->format('Y-m-d');
            $data[$formName][$date] = (float) Money::fromMinorUnits(
                (int) $price['pricePln'],
                Currencies::SETTLEMENT_CURRENCY_CODE,
                Currencies::MINOR_UNIT_SCALE
            )->toDecimalString();
        }

        return $data;
    }
}
