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

    public function testFetchLatestTotalPricePlnSumsOnlyLatestPricePerAsset(): void
    {
        $asset = $this->createGoldAsset();

        $this->createGoldPrice($asset, new DateTimeImmutable('-5 days'), 800000);
        $this->createGoldPrice($asset, new DateTimeImmutable('-1 day'), 850000);

        $total = $this->repository->fetchLatestTotalPricePln();

        self::assertSame(850000, $total);
    }

    public function testFetchLatestTotalPricePlnSumsAcrossMultipleAssets(): void
    {
        $assetA = $this->createGoldAsset();
        $assetB = $this->createGoldAsset();

        $this->createGoldPrice($assetA, new DateTimeImmutable('-1 day'), 800000);
        $this->createGoldPrice($assetB, new DateTimeImmutable('-1 day'), 500000);

        $total = $this->repository->fetchLatestTotalPricePln();

        self::assertSame(1300000, $total);
    }

    public function testFetchLatestTotalPricePlnUsesHighestIdToBreakLatestDateTiesPerAsset(): void
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

        self::assertSame(1300000, $this->repository->fetchLatestTotalPricePln());
    }

    public function testFetchLatestTotalPricePlnReturnsZeroWhenNoPricesExist(): void
    {
        $this->createGoldAsset();

        $total = $this->repository->fetchLatestTotalPricePln();

        self::assertSame(0, $total);
    }

    public function testFindLatestOrderedSortsByDateDescending(): void
    {
        $asset = $this->createGoldAsset();

        $older = $this->createGoldPrice($asset, new DateTimeImmutable('-5 days'), 800000);
        $newer = $this->createGoldPrice($asset, new DateTimeImmutable('-1 day'), 850000);

        $result = $this->repository->findLatestOrdered();

        self::assertSame($newer->getId(), $result[0]->getId());
        self::assertSame($older->getId(), $result[1]->getId());
    }

    public function testFindLatestOrderedRespectsDefaultLimit(): void
    {
        $asset = $this->createGoldAsset();

        for ($i = 0; $i < 25; ++$i) {
            $this->createGoldPrice($asset, new DateTimeImmutable("-{$i} days"), 800000 + $i);
        }

        $result = $this->repository->findLatestOrdered();

        self::assertCount(20, $result);
    }

    public function testFindLatestOrderedRespectsExplicitLimit(): void
    {
        $asset = $this->createGoldAsset();

        for ($i = 0; $i < 15; ++$i) {
            $this->createGoldPrice($asset, new DateTimeImmutable("-{$i} days"), 800000 + $i);
        }

        $result = $this->repository->findLatestOrdered(10);

        self::assertCount(10, $result);
    }

    public function testFindLatestOrderedEagerLoadsGoldAssetWithoutExtraQueries(): void
    {
        // Regression guard: findLatestOrdered() must addSelect('ga'), not just join() it — without
        // addSelect, reading goldAsset.description (as the Twig list does) would trigger one
        // lazy-load query per distinct asset instead of zero.
        for ($i = 0; $i < 3; ++$i) {
            $this->createGoldPrice($this->createGoldAsset(), new DateTimeImmutable("-{$i} days"), 800000 + $i);
        }
        $this->em()->clear();

        /** @var QueryCounter $queryCounter */
        $queryCounter = self::getContainer()->get(QueryCounter::class);
        $queryCounter->reset();

        $result = $this->repository->findLatestOrdered();
        foreach ($result as $price) {
            // Simulates exactly what templates/gold_price/index.html.twig reads: p.goldAsset.description.
            $price->getGoldAsset()->getDescription();
        }

        self::assertSame(1, $queryCounter->count, 'Expected exactly one query; goldAsset access triggered extra lazy-load queries.');
    }

    public function testFindHistoricalPricesForChartConvertsMinorUnitsToDecimal(): void
    {
        $asset = $this->createGoldAsset();
        $date = new DateTimeImmutable('-1 day');

        $this->createGoldPrice($asset, $date, 1680000);

        $result = $this->repository->findHistoricalPricesForChart();

        $formName = $asset->getForm()->getName();
        self::assertSame(16800.0, $result[$formName][$date->format('Y-m-d')]);
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
