<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Repository\CurrencyExchangeRateRepository;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CurrencyExchangeRateRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private CurrencyExchangeRateRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var CurrencyExchangeRateRepository $repository */
        $repository = $this->em()->getRepository(CurrencyExchangeRate::class);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testFetchLatestRatesToPlnByBankReturnsExpectedShape(): void
    {
        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->findOneBy(['code' => 'CHF']);
        if ($currency === null) {
            $currency = new Currency('CHF');
            $this->em()->persist($currency);
        }

        $testBankName = 'Test Bank ' . uniqid();
        $bank = new Bank($testBankName);
        $this->em()->persist($bank);

        $oldRate = new CurrencyExchangeRate($bank, $currency, new DateTimeImmutable('-2 days'), 400);
        $this->em()->persist($oldRate);

        $latestRate = new CurrencyExchangeRate($bank, $currency, new DateTimeImmutable('-1 day'), 455);
        $this->em()->persist($latestRate);

        $this->em()->flush();

        $result = $this->repository->fetchLatestRatesToPlnByBank();

        self::assertArrayHasKey($testBankName, $result);
        self::assertArrayHasKey('CHF', $result[$testBankName]);
        self::assertSame(455, $result[$testBankName]['CHF']);
    }

    public function testFindAllOrderedForListReturnsResultsOrderedByDateDescending(): void
    {
        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('CHF');

        $older = $this->createRate($bank, $currency, new DateTimeImmutable('-5 days'), 400);
        $newer = $this->createRate($bank, $currency, new DateTimeImmutable('-1 day'), 450);

        $result = $this->repository->findAllOrderedForList();

        self::assertSame($newer->getId(), $result[0]->getId());
    }

    public function testFindAllOrderedForListRespectsMaxResultsLimit(): void
    {
        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('CHF');

        for ($i = 0; $i < 25; ++$i) {
            $this->createRate($bank, $currency, new DateTimeImmutable("-{$i} days"), 400 + $i);
        }

        $result = $this->repository->findAllOrderedForList();

        self::assertCount(20, $result);
    }

    public function testFindHistoricalRatesForChartReturnsOnlyChfRates(): void
    {
        $bank = $this->createBank();
        $chf = $this->findOrCreateCurrency('CHF');
        $pln = $this->findOrCreateCurrency('PLN');

        $this->createRate($bank, $chf, new DateTimeImmutable('-1 day'), 45500);

        $result = $this->repository->findHistoricalRatesForChart();

        self::assertArrayHasKey($bank->getName(), $result);
    }

    public function testFindHistoricalRatesForChartConvertsMinorUnitsToDecimal(): void
    {
        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('CHF');
        $date = new DateTimeImmutable('-1 day');

        $this->createRate($bank, $currency, $date, 45500);

        $result = $this->repository->findHistoricalRatesForChart();

        self::assertSame(4.55, $result[$bank->getName()][$date->format('Y-m-d')]);
    }

    private function createBank(): Bank
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);
        $this->em()->flush();

        return $bank;
    }

    private function findOrCreateCurrency(string $code): Currency
    {
        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->findOneBy(['code' => $code]);
        if (null === $currency) {
            $currency = new Currency($code);
            $this->em()->persist($currency);
            $this->em()->flush();
        }

        return $currency;
    }

    private function createRate(Bank $bank, Currency $currency, DateTimeImmutable $date, int $rateToPln): CurrencyExchangeRate
    {
        $rate = new CurrencyExchangeRate($bank, $currency, $date, $rateToPln);
        $this->em()->persist($rate);
        $this->em()->flush();

        return $rate;
    }
}
