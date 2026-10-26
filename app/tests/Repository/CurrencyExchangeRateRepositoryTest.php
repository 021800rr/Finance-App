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

    public function testFindLatestOrderedReturnsResultsOrderedByIdDescending(): void
    {
        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('CHF');

        $older = $this->createRate($bank, $currency, new DateTimeImmutable('-5 days'), 400);
        $newer = $this->createRate($bank, $currency, new DateTimeImmutable('-1 day'), 450);

        $result = $this->repository->findLatestOrdered();

        self::assertSame($newer->getId(), $result[0]->getId());
    }

    public function testFindLatestOrderedRespectsDefaultLimit(): void
    {
        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('CHF');

        for ($i = 0; $i < 25; ++$i) {
            $this->createRate($bank, $currency, new DateTimeImmutable("-{$i} days"), 400 + $i);
        }

        $result = $this->repository->findLatestOrdered();

        self::assertCount(20, $result);
    }

    public function testFindLatestOrderedRespectsExplicitLimit(): void
    {
        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('CHF');

        for ($i = 0; $i < 15; ++$i) {
            $this->createRate($bank, $currency, new DateTimeImmutable("-{$i} days"), 400 + $i);
        }

        $result = $this->repository->findLatestOrdered(10);

        self::assertCount(10, $result);
    }

    public function testFindLatestOrderedEagerLoadsBankAndCurrencyWithoutExtraQueries(): void
    {
        // Regression guard: findLatestOrdered() must addSelect('b', 'c'), not just join() them —
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

        $result = $this->repository->findLatestOrdered();
        foreach ($result as $rate) {
            // Simulates exactly what templates/rate/index.html.twig reads: r.bank.name, r.currency.code.
            $rate->getBank()->getName();
            $rate->getCurrency()->getCode();
        }

        self::assertSame(1, $queryCounter->count, 'Expected exactly one query; bank/currency access triggered extra lazy-load queries.');
    }

    public function testFindChartRatesGroupedReturnsOnlyChfRates(): void
    {
        $bank = $this->createBank();
        $chf = $this->findOrCreateCurrency('CHF');
        $pln = $this->findOrCreateCurrency('PLN');

        $this->createRate($bank, $chf, new DateTimeImmutable('-1 day'), 45500);

        $result = $this->repository->findChartRatesGrouped();

        self::assertArrayHasKey($bank->getName(), $result);
    }

    public function testFindChartRatesGroupedConvertsMinorUnitsToDecimal(): void
    {
        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('CHF');
        $date = new DateTimeImmutable('-1 day');

        $this->createRate($bank, $currency, $date, 45500);

        $result = $this->repository->findChartRatesGrouped();

        self::assertSame(4.55, $result[$bank->getName()][$date->format('Y-m-d')]);
    }

    public function testFindChartRatesGroupedExecutesExactlyOneQuery(): void
    {
        // Regression guard: the method selects scalar columns (b.name, r.rateDate, r.rateToPln)
        // instead of hydrating entities, so there's no proxy to lazily initialize later. Guards
        // against a future refactor (e.g. selecting entities instead of scalars) reintroducing N+1.
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

        $this->repository->findChartRatesGrouped();

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
