<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\CurrencyResource;
use App\Entity\Currency;
use App\State\CurrencyProvider;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CurrencyProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private CurrencyProvider $provider;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var CurrencyProvider $provider */
        $provider = self::getContainer()->get(CurrencyProvider::class);
        $this->provider = $provider;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProvideCollectionOrdersByCodeAscending(): void
    {
        $this->ensureCurrency('PLN');
        $this->ensureCurrency('CHF');

        $result = $this->provider->provide(new GetCollection());

        $codes = array_map(static fn (CurrencyResource $r): ?string => $r->code, $result);
        $positionOfChf = array_search('CHF', $codes, true);
        $positionOfPln = array_search('PLN', $codes, true);

        self::assertIsInt($positionOfChf);
        self::assertIsInt($positionOfPln);
        self::assertLessThan($positionOfPln, $positionOfChf);
    }

    private function ensureCurrency(string $code): Currency
    {
        $repository = $this->em()->getRepository(Currency::class);
        $currency = $repository->find($code);
        if (null === $currency) {
            $currency = new Currency($code);
            $this->em()->persist($currency);
            $this->em()->flush();
        }

        return $currency;
    }
}
