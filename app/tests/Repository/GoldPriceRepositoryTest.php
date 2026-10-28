<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Currency;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Repository\GoldPriceRepository;
use App\Tests\DatabaseTestTrait;
use App\Tests\Util\QueryCounter;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GoldPriceRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private GoldPriceRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var GoldPriceRepository $repository */
        $repository = $this->em()->getRepository(GoldPrice::class);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testFetchSummarySumsOnlyLatestPricePerAsset(): void
    {
        $asset = $this->createGoldAsset();

        $this->createGoldPrice($asset, new DateTimeImmutable('-5 days'), 800000);
        $this->createGoldPrice($asset, new DateTimeImmutable('-1 day'), 850000);

        $total = $this->repository->fetchSummary();

        self::assertSame(850000, $total);
    }

    public function testFetchSummarySumsAcrossMultipleAssets(): void
    {
        $assetA = $this->createGoldAsset();
        $assetB = $this->createGoldAsset();

        $this->createGoldPrice($assetA, new DateTimeImmutable('-1 day'), 800000);
        $this->createGoldPrice($assetB, new DateTimeImmutable('-1 day'), 500000);

        $total = $this->repository->fetchSummary();

        self::assertSame(1300000, $total);
    }

    public function testFetchSummaryUsesHighestIdToBreakLatestDateTiesPerAsset(): void
    {
        $assetA = $this->createGoldAsset();
        $assetB = $this->createGoldAsset();
        $latestDateA = new DateTimeImmutable('2026-10-20');
        $latestDateB = new DateTimeImmutable('2026-10-19');

        // The last price wins a date tie, even when it is lower than the previous price.
        $this->createGoldPrice($assetA, $latestDateA, 1100000);
        $this->createGoldPrice($assetA, $latestDateA, 1000000);
        $this->createGoldPrice($assetB, $latestDateB, 200000);
        $this->createGoldPrice($assetB, $latestDateB, 300000);

        // Older prices inserted later must not win just because they have higher IDs.
        $this->createGoldPrice($assetA, new DateTimeImmutable('2026-10-01'), 9000000);
        $this->createGoldPrice($assetB, new DateTimeImmutable('2026-09-01'), 8000000);

        self::assertSame(1300000, $this->repository->fetchSummary());
    }

    public function testFetchSummaryReturnsZeroWhenNoPricesExist(): void
    {
        $this->createGoldAsset();

        $total = $this->repository->fetchSummary();

        self::assertSame(0, $total);
        self::assertSame([], $this->repository->findDataForChart());
    }

    public function testFindDataForListSortsByDateDescending(): void
    {
        $asset = $this->createGoldAsset();

        $older = $this->createGoldPrice($asset, new DateTimeImmutable('-5 days'), 800000);
        $newer = $this->createGoldPrice($asset, new DateTimeImmutable('-1 day'), 850000);

        $result = $this->repository->findDataForList();

        self::assertSame($newer->getId(), $result[0]->getId());
        self::assertSame($older->getId(), $result[1]->getId());
    }

    public function testFindDataForListUsesDateThenIdAcrossAssets(): void
    {
        $assetA = $this->createGoldAsset();
        $assetB = $this->createGoldAsset();
        $day = new DateTimeImmutable('2026-10-26');
        $first = $this->createGoldPrice($assetA, $day, 1100000);
        $second = $this->createGoldPrice($assetB, $day, 1000000);
        $backfilled = $this->createGoldPrice($assetA, new DateTimeImmutable('2026-10-25'), 900000);

        $result = $this->repository->findDataForList();

        self::assertSame(
            [$second->getId(), $first->getId(), $backfilled->getId()],
            array_map(static fn (GoldPrice $price): int => $price->getId(), $result),
        );
    }

    public function testFindDataForListRespectsDefaultLimit(): void
    {
        $asset = $this->createGoldAsset();

        for ($i = 0; $i < 25; ++$i) {
            $this->createGoldPrice($asset, new DateTimeImmutable("-{$i} days"), 800000 + $i);
        }

        $result = $this->repository->findDataForList();

        self::assertCount(20, $result);
    }

    public function testFindDataForListRespectsExplicitLimit(): void
    {
        $asset = $this->createGoldAsset();

        for ($i = 0; $i < 15; ++$i) {
            $this->createGoldPrice($asset, new DateTimeImmutable("-{$i} days"), 800000 + $i);
        }

        $result = $this->repository->findDataForList(10);

        self::assertCount(10, $result);
    }

    public function testFindDataForListEagerLoadsGoldAssetWithoutExtraQueries(): void
    {
        // Regression guard: findDataForList() must addSelect('ga'), not just join() it — without
        // addSelect, reading goldAsset.description (as the Twig list does) would trigger one
        // lazy-load query per distinct asset instead of zero.
        for ($i = 0; $i < 3; ++$i) {
            $this->createGoldPrice($this->createGoldAsset(), new DateTimeImmutable("-{$i} days"), 800000 + $i);
        }
        $this->em()->clear();

        /** @var QueryCounter $queryCounter */
        $queryCounter = self::getContainer()->get(QueryCounter::class);
        $queryCounter->reset();

        $result = $this->repository->findDataForList();
        foreach ($result as $price) {
            // Simulates exactly what templates/gold_price/index.html.twig reads: p.goldAsset.description.
            $price->getGoldAsset()->getDescription();
        }

        self::assertSame(1, $queryCounter->count, 'Expected exactly one query; goldAsset access triggered extra lazy-load queries.');
    }

    public function testFindDataForChartReturnsScalarRowsWithMinorUnits(): void
    {
        $asset = $this->createGoldAsset();
        $date = new DateTimeImmutable('-1 day');

        $price = $this->createGoldPrice($asset, $date, 1680000);

        $result = $this->repository->findDataForChart();

        self::assertSame([
            [
                'id' => $price->getId(),
                'gold_asset_id' => $asset->getId(),
                'asset_description' => $asset->getDescription(),
                'currency_code' => 'PLN',
                'price_date' => $date->format('Y-m-d'),
                'price_pln_minor_units' => 1680000,
            ],
        ], $result);
    }

    public function testChartKeepsAssetsSeparateAndUsesTheSameDailyPricesAsTheValuation(): void
    {
        $assetA = $this->createGoldAsset();
        // Same form, weight and description: only the asset ID distinguishes these two assets.
        $assetB = new GoldAsset($assetA->getForm(), $assetA->getOunces(), $assetA->getDescription());
        $this->em()->persist($assetB);
        $this->em()->flush();
        $day = new DateTimeImmutable('2026-10-26');

        $this->createGoldPrice($assetA, $day, 1100000);
        $latestA = $this->createGoldPrice($assetA, $day, 1000000);
        $this->createGoldPrice($assetB, $day, 1200000);
        $latestB = $this->createGoldPrice($assetB, $day, 1300000);
        $historical = $this->createGoldPrice($assetA, new DateTimeImmutable('2026-10-25'), 9000000);

        $prices = $this->repository->findDataForChart();

        self::assertSame([$historical->getId(), $latestA->getId(), $latestB->getId()], array_column($prices, 'id'));
        self::assertSame(
            [$assetA->getId(), $assetA->getId(), $assetB->getId()],
            array_column($prices, 'gold_asset_id'),
        );
        self::assertSame([9000000, 1000000, 1300000], array_column($prices, 'price_pln_minor_units'));
        self::assertSame(2300000, $this->repository->fetchSummary());
    }

    public function testDeletingTheLatestDailyPriceRevealsThePreviousQuote(): void
    {
        $asset = $this->createGoldAsset();
        $day = new DateTimeImmutable('2026-09-15');
        $this->createGoldPrice($asset, $day, 850000);
        $latest = $this->createGoldPrice($asset, $day, 860000);

        $chart = $this->repository->findDataForChart();
        self::assertSame(860000, $chart[0]['price_pln_minor_units']);
        self::assertSame(860000, $this->repository->fetchSummary());

        $this->em()->remove($latest);
        $this->em()->flush();

        $chart = $this->repository->findDataForChart();
        self::assertSame(850000, $chart[0]['price_pln_minor_units']);
        self::assertSame(850000, $this->repository->fetchSummary());
    }

    public function testSaveStoresOnlyTheCalendarDateAndReloadsAtMidnight(): void
    {
        $price = $this->createGoldPrice(
            $this->createGoldAsset(),
            new DateTimeImmutable('2026-10-26 12:34:56'),
            500000,
        );
        $id = $price->getId();

        $storedDate = $this->em()->getConnection()->fetchOne(
            'SELECT price_date FROM gold_price WHERE id = ?',
            [$id],
        );
        self::assertSame('2026-10-26', $storedDate);

        $this->em()->clear();
        $reloaded = $this->repository->find($id);

        self::assertNotNull($reloaded);
        self::assertSame('2026-10-26 00:00:00', $reloaded->getPriceDate()->format('Y-m-d H:i:s'));
    }

    private function createGoldAsset(): GoldAsset
    {
        $goldForm = new GoldForm('Test Form ' . uniqid());
        $this->em()->persist($goldForm);

        $asset = new GoldAsset($goldForm, 10000, 'Test');
        $this->em()->persist($asset);
        $this->em()->flush();

        return $asset;
    }

    private function createGoldPrice(GoldAsset $asset, DateTimeImmutable $date, int $pricePln): GoldPrice
    {
        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find('PLN');
        if (null === $currency) {
            $currency = new Currency('PLN');
            $this->em()->persist($currency);
        }

        $price = new GoldPrice($asset, $date, $pricePln, $currency);
        $this->em()->persist($price);
        $this->em()->flush();

        return $price;
    }
}
