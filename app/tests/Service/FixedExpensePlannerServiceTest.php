<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Repository\AccountOperationRepository;
use App\Repository\AccountRepository;
use App\Repository\FixedExpenseRepository;
use App\Service\FixedExpensePlannerService;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FixedExpensePlannerServiceTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    private FixedExpensePlannerService $planner;
    private Account $mainAccount;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        $this->mainAccount = $this->createAccount(main: true);

        $this->planner = $this->buildPlanner();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testPlanNextMonthCreatesOperationForEachFixedExpense(): void
    {
        $this->createFixedExpense(day: 10, amount: -15000, description: 'Rent');
        $this->createFixedExpense(day: 20, amount: -5000, description: 'Internet');

        $result = $this->planner->planNextMonth();

        self::assertSame(2, $result->createdCount);
        self::assertSame(
            new \DateTimeImmutable('first day of this month')->modify('+1 month')->format('Y-m'),
            $result->month->format('Y-m'),
        );
    }

    public function testPlanNextMonthCreatesOperationWithCorrectFields(): void
    {
        $fixedExpense = $this->createFixedExpense(day: 15, amount: -15050, description: 'Rent');

        $this->planner->planNextMonth();

        $targetMonth = new \DateTimeImmutable('first day of this month')->modify('+1 month');
        $expectedDate = $this->buildExpectedDate((int) $targetMonth->format('Y'), (int) $targetMonth->format('m'), 15);

        $operations = $this->em()->getRepository(AccountOperation::class)->findAll();
        self::assertCount(1, $operations);

        $operation = $operations[0];
        self::assertSame($this->mainAccount->getId(), $operation->getAccount()->getId());
        self::assertSame($fixedExpense->getContractor()->getId(), $operation->getContractor()->getId());
        self::assertSame(-15050, $operation->getAmount());
        self::assertSame('Rent', $operation->getDescription());
        self::assertSame($expectedDate->format('Y-m-d'), $operation->getOperationDate()->format('Y-m-d'));
    }

    public function testPlanNextMonthThrowsWhenNoAccountIsMarkedMain(): void
    {
        $this->mainAccount->setMain(false);
        $this->em()->flush();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No account is marked as the main account. Mark one account as main first.');

        $this->planner->planNextMonth();
    }

    public function testPlanNextMonthThrowsAndCreatesNothingWhenDuplicateExists(): void
    {
        $targetMonth = new \DateTimeImmutable('first day of this month')->modify('+1 month');
        $year = (int) $targetMonth->format('Y');
        $month = (int) $targetMonth->format('m');

        $contractor = $this->createContractor();
        $duplicateDate = $this->buildExpectedDate($year, $month, 10);

        $this->createOperation($this->mainAccount, -15000, $duplicateDate, $contractor);

        $this->createFixedExpense(day: 10, amount: -15000, contractor: $contractor, description: 'Test');

        $this->assertPlanningIsRefusedWith('/Suspected duplicate/');
    }

    public function testPlanNextMonthCreatesNothingWhenOnlyALaterExpenseIsADuplicate(): void
    {
        $targetMonth = new \DateTimeImmutable('first day of next month');
        $contractor = $this->createContractor();
        $duplicateDate = $this->buildExpectedDate((int) $targetMonth->format('Y'), (int) $targetMonth->format('m'), 20);
        $this->createOperation($this->mainAccount, -15000, $duplicateDate, $contractor);
        // Checked first, because expenses are planned by day: this one alone could be booked.
        $this->createFixedExpense(day: 5, amount: -5000, description: 'New one');
        $this->createFixedExpense(day: 20, amount: -15000, contractor: $contractor, description: 'Test');

        $this->assertPlanningIsRefusedWith('/Suspected duplicate/');
    }

    public function testPlanNextMonthRefusesAMainAccountInAnotherCurrencyThanTheFixedExpenses(): void
    {
        $this->createFixedExpense(day: 10, amount: -15000, description: 'Rent');
        $this->createAccount(currency: 'CHF', main: true);

        $this->assertPlanningIsRefusedWith('/is in CHF/');
    }

    public function testPlanNextMonthRefusesAMainAccountOfAnotherHousehold(): void
    {
        $this->createFixedExpense(day: 10, amount: -15000, description: 'Rent');
        $this->createAccount(household: $this->household(false), main: true);

        $this->assertPlanningIsRefusedWith('/only be planned on our own accounts/');
    }

    private function buildPlanner(): FixedExpensePlannerService
    {
        $container = self::getContainer();

        /** @var AccountRepository $accountRepository */
        $accountRepository = $container->get(AccountRepository::class);

        /** @var FixedExpenseRepository $fixedExpenseRepository */
        $fixedExpenseRepository = $container->get(FixedExpenseRepository::class);

        /** @var AccountOperationRepository $accountOperationRepository */
        $accountOperationRepository = $container->get(AccountOperationRepository::class);

        return new FixedExpensePlannerService(
            $accountRepository,
            $fixedExpenseRepository,
            $accountOperationRepository,
            $this->em(),
        );
    }

    private function buildExpectedDate(int $year, int $month, int $day): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day)
        );
        if (false === $date) {
            throw new \RuntimeException('Failed to build expected date in test.');
        }

        return $date;
    }

    private function assertPlanningIsRefusedWith(string $messagePattern): void
    {
        $operationsBefore = $this->em()->getRepository(AccountOperation::class)->count([]);

        try {
            $this->planner->planNextMonth();
            self::fail('Planning should have been refused.');
        } catch (\RuntimeException $exception) {
            self::assertMatchesRegularExpression($messagePattern, $exception->getMessage());
        }

        self::assertSame($operationsBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }
}
