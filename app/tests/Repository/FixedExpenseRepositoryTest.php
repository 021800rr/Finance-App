<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Constant\Currencies;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\FixedExpense;
use App\Repository\FixedExpenseRepository;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FixedExpenseRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private FixedExpenseRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var FixedExpenseRepository $repository */
        $repository = $this->em()->getRepository(FixedExpense::class);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testFindAllOrderedSortsByDayAscending(): void
    {
        $this->createFixedExpense(28, -100);
        $this->createFixedExpense(1, -200);
        $this->createFixedExpense(15, -300);

        $result = $this->repository->findAllOrdered();

        self::assertSame(1, $result[0]->getDay());
        self::assertSame(15, $result[1]->getDay());
        self::assertSame(28, $result[2]->getDay());
    }

    public function testFindAllOrderedSortsBySameDayByIdDescending(): void
    {
        $first = $this->createFixedExpense(10, -100);
        $second = $this->createFixedExpense(10, -200);

        $result = $this->repository->findAllOrdered();

        $sameDay = array_values(array_filter($result, static fn (FixedExpense $fe) => 10 === $fe->getDay()));

        self::assertSame($second->getId(), $sameDay[0]->getId());
        self::assertSame($first->getId(), $sameDay[1]->getId());
    }

    public function testFindAllOrderedReturnsEmptyArrayWhenNoExpenses(): void
    {
        $result = $this->repository->findAllOrdered();

        self::assertSame([], $result);
    }

    public function testFindAllOrderedIncludesContractor(): void
    {
        $expense = $this->createFixedExpense(5, -150);

        $result = $this->repository->findAllOrdered();

        self::assertSame($expense->getContractor()->getId(), $result[0]->getContractor()->getId());
    }

    private function createFixedExpense(int $day, int $amount): FixedExpense
    {
        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find(Currencies::SETTLEMENT_CURRENCY_CODE);
        if (null === $currency) {
            $currency = new Currency(Currencies::SETTLEMENT_CURRENCY_CODE);
            $this->em()->persist($currency);
        }

        $fixedExpense = new FixedExpense($contractor, $day, $amount, $currency);
        $this->em()->persist($fixedExpense);
        $this->em()->flush();

        return $fixedExpense;
    }
}
