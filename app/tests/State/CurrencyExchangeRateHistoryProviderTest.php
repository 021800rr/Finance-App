<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\CurrencyExchangeRateResource;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\State\CurrencyExchangeRateHistoryProvider;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CurrencyExchangeRateHistoryProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private CurrencyExchangeRateHistoryProvider $provider;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var CurrencyExchangeRateHistoryProvider $provider */
        $provider = self::getContainer()->get(CurrencyExchangeRateHistoryProvider::class);
        $this->provider = $provider;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProvideReturnsAllRatesWithoutLimit(): void
    {
        $ids = [];
        // One more than CurrencyExchangeRateProvider's internal LATEST_LIMIT of 10, to prove
        // this provider is not bound by that limit.
        for ($i = 0; $i < 11; ++$i) {
            $ids[] = $this->createRate(new \DateTimeImmutable("2026-01-{$this->twoDigit($i + 1)}"))->getId();
        }

        /** @var list<CurrencyExchangeRateResource> $result */
        $result = $this->provider->provide(new GetCollection());

        $returnedIds = array_map(static fn (CurrencyExchangeRateResource $r): ?int => $r->id, $result);
        foreach ($ids as $id) {
            self::assertContains($id, $returnedIds);
        }
    }

    public function testProvideOrdersByRateDateAscendingThenId(): void
    {
        $later = $this->createRate(new \DateTimeImmutable('2026-02-01'));
        $earlier = $this->createRate(new \DateTimeImmutable('2026-01-01'));

        /** @var list<CurrencyExchangeRateResource> $result */
        $result = $this->provider->provide(new GetCollection());

        $ids = array_map(static fn (CurrencyExchangeRateResource $r): ?int => $r->id, $result);
        $positionOfEarlier = array_search($earlier->getId(), $ids, true);
        $positionOfLater = array_search($later->getId(), $ids, true);

        self::assertIsInt($positionOfEarlier);
        self::assertIsInt($positionOfLater);
        self::assertLessThan($positionOfLater, $positionOfEarlier);
    }

    public function testProvideExcludesUntrackedCurrencies(): void
    {
        $tracked = $this->createRate(new \DateTimeImmutable(), 'CHF');
        $untracked = $this->createRate(new \DateTimeImmutable(), 'DKK');

        /** @var list<CurrencyExchangeRateResource> $result */
        $result = $this->provider->provide(new GetCollection());

        $ids = array_map(static fn (CurrencyExchangeRateResource $r): ?int => $r->id, $result);
        self::assertContains($tracked->getId(), $ids);
        self::assertNotContains($untracked->getId(), $ids);
    }

    private function createRate(\DateTimeImmutable $date, string $currencyCode = 'CHF'): CurrencyExchangeRate
    {
        $bank = new Bank('Test Bank '.uniqid());
        $this->em()->persist($bank);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find($currencyCode);
        if (null === $currency) {
            $currency = new Currency($currencyCode);
            $this->em()->persist($currency);
        }

        $rate = new CurrencyExchangeRate($bank, $currency, $date, 43210);
        $this->em()->persist($rate);
        $this->em()->flush();

        return $rate;
    }

    private function twoDigit(int $n): string
    {
        return str_pad((string) $n, 2, '0', \STR_PAD_LEFT);
    }
}
