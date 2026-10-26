<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Repository\CurrencyExchangeRateRepository;
use App\Tests\DatabaseTestTrait;
use App\Tests\Util\QueryCounter;
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

    public function testFetchSummaryReturnsExpectedShape(): void
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

        $result = $this->repository->fetchSummary();

        self::assertArrayHasKey($testBankName, $result);
        self::assertArrayHasKey('CHF', $result[$testBankName]);
        self::assertSame(455, $result[$testBankName]['CHF']);
    }

    public function testFindDataForListReturnsResultsOrderedByDateThenIdDescending(): void
    {
        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('CHF');

        $day = new DateTimeImmutable('2026-10-26');
        $first = $this->createRate($bank, $currency, $day, 45000);
        $second = $this->createRate($bank, $currency, $day, 44000);
        $backfilled = $this->createRate($bank, $currency, new DateTimeImmutable('2026-10-25'), 40000);

        $result = $this->repository->findDataForList();

        self::assertSame(
            [$second->getId(), $first->getId(), $backfilled->getId()],
            array_map(static fn (CurrencyExchangeRate $rate): int => $rate->getId(), $result),
        );
    }

    public function testCurrentRatesAndChartUseDateThenIdPerBankAndCurrency(): void
    {
        $bankA = $this->createBank();
        $bankB = $this->createBank();
        $chf = $this->findOrCreateCurrency('CHF');
        $eur = $this->findOrCreateCurrency('EUR');
        $day = new DateTimeImmutable('2026-10-26');

        $this->createRate($bankA, $chf, $day, 45000);
        $latestA = $this->createRate($bankA, $chf, $day, 43000);
        $latestB = $this->createRate($bankB, $chf, $day, 44000);
        $this->createRate($bankA, $eur, $day, 48000);
        $this->createRate($bankA, $eur, $day, 47000);
        $historical = $this->createRate($bankA, $chf, new DateTimeImmutable('2026-10-25'), 90000);
        $this->createRate($bankA, $eur, new DateTimeImmutable('2026-10-25'), 80000);

        $rates = $this->repository->fetchSummary();
        $chart = $this->repository->findDataForChart();

        self::assertSame(['CHF' => 43000, 'EUR' => 47000], $rates[$bankA->getName()]);
        self::assertSame(['CHF' => 44000], $rates[$bankB->getName()]);
        self::assertSame([$historical->getId(), $latestA->getId(), $latestB->getId()], array_column($chart, 'id'));
        self::assertSame([90000, 43000, 44000], array_column($chart, 'rate_to_pln_minor_units'));
    }

    public function testFindDataForListRespectsDefaultLimit(): void
    {
        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('CHF');

        for ($i = 0; $i < 25; ++$i) {
            $this->createRate($bank, $currency, new DateTimeImmutable("-{$i} days"), 400 + $i);
        }

        $result = $this->repository->findDataForList();

        self::assertCount(20, $result);
    }

    public function testFindDataForListRespectsExplicitLimit(): void
    {
        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('CHF');

        for ($i = 0; $i < 15; ++$i) {
            $this->createRate($bank, $currency, new DateTimeImmutable("-{$i} days"), 400 + $i);
        }

        $result = $this->repository->findDataForList(10);

        self::assertCount(10, $result);
    }

    public function testFindDataForListEagerLoadsBankAndCurrencyWithoutExtraQueries(): void
    {
        // Regression guard: findDataForList() must addSelect('b', 'c'), not just join() them —
        // without addSelect, reading bank.name / currency.code (as the Twig list does) would trigger
        // one lazy-load query per distinct bank/currency instead of zero.
        for ($i = 0; $i < 3; ++$i) {
            $this->createRate(
                $this->createBank(),
                $this->findOrCreateCurrency('CHF'),
                new DateTimeImmutable("-{$i} days"),
                rateToPln: 400 + $i);
        }
        $this->em()->clear();

        /** @var QueryCounter $queryCounter */
        $queryCounter = self::getContainer()->get(QueryCounter::class);
        $queryCounter->reset();

        $result = $this->repository->findDataForList();
        foreach ($result as $rate) {
            // Simulates exactly what templates/rate/index.html.twig reads: r.bank.name, r.currency.code.
            $rate->getBank()->getName();
            $rate->getCurrency()->getCode();
        }

        self::assertSame(1, $queryCounter->count, 'Expected exactly one query; bank/currency access triggered extra lazy-load queries.');
    }

    public function testFindAllHistoricalRatesReturnsOnlyChfRates(): void
    {
        $bank = $this->createBank();
        $chf = $this->findOrCreateCurrency('CHF');
        $eur = $this->findOrCreateCurrency('EUR');

        $tracked = $this->createRate($bank, $chf, new DateTimeImmutable('-1 day'), 45500);
        $this->createRate($bank, $eur, new DateTimeImmutable('-1 day'), 42000);

        $result = $this->repository->findDataForChart();

        self::assertSame([$tracked->getId()], array_column($result, 'id'));
        self::assertSame(['CHF'], array_column($result, 'currency_code'));
    }

    public function testFindAllHistoricalRatesReturnsScalarRowsWithMinorUnits(): void
    {
        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('CHF');
        $date = new DateTimeImmutable('-1 day');

        $rate = $this->createRate($bank, $currency, $date, 45500);

        $result = $this->repository->findDataForChart();

        self::assertSame([[
            'id' => $rate->getId(),
            'bank_id' => $bank->getId(),
            'bank_name' => $bank->getName(),
            'currency_code' => 'CHF',
            'rate_date' => $date->format('Y-m-d'),
            'rate_to_pln_minor_units' => 45500,
        ]], $result);
    }

    public function testFindAllHistoricalRatesExecutesExactlyOneQuery(): void
    {
        // Reading the selected daily rates and bank names must use a single query.
        for ($i = 0; $i < 3; ++$i) {
            $this->createRate(
                $this->createBank(),
                $this->findOrCreateCurrency('CHF'),
                new DateTimeImmutable("-{$i} days"),
                rateToPln: 400 + $i);
        }
        $this->em()->clear();

        /** @var QueryCounter $queryCounter */
        $queryCounter = self::getContainer()->get(QueryCounter::class);
        $queryCounter->reset();
        $this->repository->findDataForChart();

        self::assertSame(1, $queryCounter->count, 'Expected exactly one query.');
    }

    public function testSaveStoresOnlyTheCalendarDateAndReloadsAtMidnight(): void
    {
        $rate = $this->createRate(
            $this->createBank(),
            $this->findOrCreateCurrency('CHF'),
            new DateTimeImmutable('2026-10-26 12:34:56'),
            43210,
        );
        $id = $rate->getId();

        $storedDate = $this->em()->getConnection()->fetchOne(
            'SELECT rate_date FROM currency_exchange_rate WHERE id = ?',
            [$id],
        );
        self::assertSame('2026-10-26', $storedDate);

        $this->em()->clear();
        $reloaded = $this->repository->find($id);

        self::assertNotNull($reloaded);
        self::assertSame('2026-10-26 00:00:00', $reloaded->getRateDate()->format('Y-m-d H:i:s'));
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
