<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Currency;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Repository\GoldPriceRepository;
use App\Tests\DatabaseTestTrait;
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

    public function testFetchLatestTotalPricePlnReturnsZeroWhenNoPricesExist(): void
    {
        $this->createGoldAsset();

        $total = $this->repository->fetchLatestTotalPricePln();

        self::assertSame(0, $total);
    }

    public function testFindAllOrderedSortsByDateDescending(): void
    {
        $asset = $this->createGoldAsset();

        $older = $this->createGoldPrice($asset, new DateTimeImmutable('-5 days'), 800000);
        $newer = $this->createGoldPrice($asset, new DateTimeImmutable('-1 day'), 850000);

        $result = $this->repository->findAllOrdered();

        self::assertSame($newer->getId(), $result[0]->getId());
        self::assertSame($older->getId(), $result[1]->getId());
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
