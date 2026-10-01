<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\CurrencyExchangeRateResource;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\State\CurrencyExchangeRateProvider;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CurrencyExchangeRateProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private CurrencyExchangeRateProvider $provider;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var CurrencyExchangeRateProvider $provider */
        $provider = self::getContainer()->get(CurrencyExchangeRateProvider::class);
        $this->provider = $provider;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProvideReturnsResourceForExistingId(): void
    {
        $rate = $this->createRate();

        $result = $this->provider->provide(new Get(), ['id' => $rate->getId()]);

        self::assertInstanceOf(CurrencyExchangeRateResource::class, $result);
        self::assertSame($rate->getId(), $result->id);
        self::assertSame($rate->getBank()->getId(), $result->bankId);
    }

    public function testProvideReturnsNullForNonExistentId(): void
    {
        $result = $this->provider->provide(new Get(), ['id' => 999999999]);

        self::assertNull($result);
    }

    public function testProvideCollectionOrdersByIdDescending(): void
    {
        $first = $this->createRate();
        $second = $this->createRate();

        /** @var list<CurrencyExchangeRateResource> $result */
        $result = $this->provider->provide(new GetCollection());

        self::assertNotEmpty($result);
        self::assertSame($second->getId(), $result[0]->id);

        $ids = array_map(static fn (CurrencyExchangeRateResource $r): ?int => $r->id, $result);
        $positionOfFirst = array_search($first->getId(), $ids, true);
        $positionOfSecond = array_search($second->getId(), $ids, true);
        self::assertIsInt($positionOfFirst);
        self::assertIsInt($positionOfSecond);
        self::assertLessThan($positionOfFirst, $positionOfSecond);
    }

    public function testProvideCollectionRespectsTheLatestLimit(): void
    {
        $ids = [];
        // One more than the provider's internal LATEST_LIMIT of 10.
        for ($i = 0; $i < 11; ++$i) {
            $ids[] = $this->createRate()->getId();
        }

        /** @var list<CurrencyExchangeRateResource> $result */
        $result = $this->provider->provide(new GetCollection());

        self::assertCount(10, $result);
        // The oldest of the 11 created rates must have been dropped.
        $returnedIds = array_map(static fn (CurrencyExchangeRateResource $r): ?int => $r->id, $result);
        self::assertNotContains($ids[0], $returnedIds);
        self::assertContains($ids[count($ids) - 1], $returnedIds);
    }

    private function createRate(): CurrencyExchangeRate
    {
        $bank = new Bank('Test Bank '.uniqid());
        $this->em()->persist($bank);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find('CHF');
        if (null === $currency) {
            $currency = new Currency('CHF');
            $this->em()->persist($currency);
        }

        $rate = new CurrencyExchangeRate($bank, $currency, new \DateTimeImmutable(), 43210);
        $this->em()->persist($rate);
        $this->em()->flush();

        return $rate;
    }
}
