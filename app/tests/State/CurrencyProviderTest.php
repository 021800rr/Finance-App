<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\CurrencyResource;
use App\Entity\Currency;
use App\State\CurrencyProvider;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    public function testProvideReturnsResourceForExistingCode(): void
    {
        $currency = $this->ensureCurrency('PLN');

        $result = $this->provider->provide(new Get(), ['code' => 'PLN']);

        self::assertInstanceOf(CurrencyResource::class, $result);
        self::assertSame($currency->getCode(), $result->code);
    }

    public function testProvideThrowsNotFoundForNonExistentCode(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Currency not found.');

        $this->provider->provide(new Get(), ['code' => 'ZZZ']);
    }

    public function testProvideCollectionOrdersByCodeAscending(): void
    {
        $this->ensureCurrency('PLN');
        $this->ensureCurrency('CHF');

        /** @var list<CurrencyResource> $result */
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
