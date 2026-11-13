<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Currency;
use App\Repository\CurrencyRepository;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CurrencyRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private CurrencyRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var CurrencyRepository $repository */
        $repository = $this->em()->getRepository(Currency::class);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testFindPlnOrThrowReturnsPlnCurrencyFromFixtures(): void
    {
        $currency = $this->repository->findPlnOrThrow();

        self::assertSame('PLN', $currency->getCode());
    }
}
