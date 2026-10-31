<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\FixedExpenseResource;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\FixedExpense;
use App\State\FixedExpenseProvider;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FixedExpenseProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private FixedExpenseProvider $provider;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var FixedExpenseProvider $provider */
        $provider = self::getContainer()->get(FixedExpenseProvider::class);
        $this->provider = $provider;

        $this->ensurePlnCurrency();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProvideReturnsResourceForExistingId(): void
    {
        $fixedExpense = $this->createFixedExpense(day: 10, amount: -20000);

        $result = $this->provider->provide(new Get(), ['id' => $fixedExpense->getId()]);

        self::assertInstanceOf(FixedExpenseResource::class, $result);
        self::assertSame($fixedExpense->getId(), $result->id);
        self::assertSame(10, $result->day);
    }

    public function testProvideThrowsNotFoundForNonExistentId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Fixed expense not found.');

        $this->provider->provide(new Get(), ['id' => 999999999]);
    }

    public function testProvideCollectionOrdersByDayAscendingThenIdDescending(): void
    {
        $dayTenFirst = $this->createFixedExpense(day: 10, amount: -10000);
        $dayFive = $this->createFixedExpense(day: 5, amount: -5000);
        $dayTenSecond = $this->createFixedExpense(day: 10, amount: -30000);

        /** @var list<FixedExpenseResource> $result */
        $result = $this->provider->provide(new GetCollection());

        $ids = array_map(static fn (FixedExpenseResource $r): ?int => $r->id, $result);

        $positionOfDayFive = array_search($dayFive->getId(), $ids, true);
        $positionOfDayTenFirst = array_search($dayTenFirst->getId(), $ids, true);
        $positionOfDayTenSecond = array_search($dayTenSecond->getId(), $ids, true);

        self::assertIsInt($positionOfDayFive);
        self::assertIsInt($positionOfDayTenFirst);
        self::assertIsInt($positionOfDayTenSecond);

        // Day 5 sorts before day 10.
        self::assertLessThan($positionOfDayTenFirst, $positionOfDayFive);
        // Within day 10, the later-created (higher id) expense sorts first.
        self::assertLessThan($positionOfDayTenFirst, $positionOfDayTenSecond);
    }

    private function createFixedExpense(int $day, int $amount): FixedExpense
    {
        $contractor = new Contractor('Test Contractor '.uniqid());
        $this->em()->persist($contractor);

        $currency = $this->ensurePlnCurrency();

        $fixedExpense = new FixedExpense($contractor, $day, $amount, $currency);
        $this->em()->persist($fixedExpense);
        $this->em()->flush();

        return $fixedExpense;
    }

    private function ensurePlnCurrency(): Currency
    {
        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find('PLN');
        if (null === $currency) {
            $currency = new Currency('PLN');
            $this->em()->persist($currency);
            $this->em()->flush();
        }

        return $currency;
    }
}
