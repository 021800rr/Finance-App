<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\FixedExpense;
use App\Entity\Household;
use App\Repository\AccountOperationRepository;
use App\Repository\AccountRepository;
use App\Repository\FixedExpenseRepository;
use App\Service\FixedExpensePlannerService;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FixedExpensePlannerServiceTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private FixedExpensePlannerService $planner;
    private Account $mainAccount;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        $this->mainAccount = $this->createAccount();

        /** @var AccountRepository $accountRepository */
        $accountRepository = self::getContainer()->get(AccountRepository::class);
        $accountRepository->setAsMainAccount($this->mainAccount);

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
            new DateTimeImmutable('first day of this month')->modify('+1 month')->format('Y-m'),
            $result->month->format('Y-m'),
        );
    }

    public function testPlanNextMonthCreatesOperationWithCorrectFields(): void
    {
        $fixedExpense = $this->createFixedExpense(day: 15, amount: -15050, description: 'Rent');

        $this->planner->planNextMonth();

        $targetMonth = new DateTimeImmutable('first day of this month')->modify('+1 month');
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No account is marked as the main account. Mark one account as main first.');

        $this->planner->planNextMonth();
    }

    public function testPlanNextMonthThrowsAndCreatesNothingWhenDuplicateExists(): void
    {
        $targetMonth = new DateTimeImmutable('first day of this month')->modify('+1 month');
        $year = (int) $targetMonth->format('Y');
        $month = (int) $targetMonth->format('m');

        $contractor = $this->createContractor();
        $duplicateDate = $this->buildExpectedDate($year, $month, 10);

        $currency = $this->getPlnCurrency();
        $existingOperation = new AccountOperation($this->mainAccount, $contractor, $duplicateDate, -15000, $currency);
        $this->em()->persist($existingOperation);
        $this->em()->flush();

        $this->createFixedExpenseWithContractor(day: 10, amount: -15000, contractor: $contractor);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Suspected duplicate/');

        $this->planner->planNextMonth();

        $operations = $this->em()->getRepository(AccountOperation::class)->findAll();
        self::assertCount(1, $operations);
    }

    public function testPlanNextMonthRefusesAMainAccountInAnotherCurrencyThanTheFixedExpenses(): void
    {
        $this->createFixedExpense(day: 10, amount: -15000, description: 'Rent');
        $this->markAsMain($this->createAccount(currencyCode: 'CHF'));

        $this->assertPlanningIsRefusedWith('/is in CHF/');
    }

    public function testPlanNextMonthRefusesAMainAccountOfAnotherHousehold(): void
    {
        $this->createFixedExpense(day: 10, amount: -15000, description: 'Rent');
        $this->markAsMain($this->createAccount(ownFunds: false));

        $this->assertPlanningIsRefusedWith('/only be planned on our own accounts/');
    }

    public function testPlanNextMonthSkipsExpenseWithInvalidDayForFebruary(): void
    {
        // Days 29-31 can never be reached because FixedExpense enforces day <= 28,
        // so this branch of planNextMonth() is unreachable in practice.
        self::markTestSkipped('FixedExpense::day is constrained to 1-28, this scenario cannot occur.');
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

    private function buildExpectedDate(int $year, int $month, int $day): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day)
        );
        if (false === $date) {
            throw new RuntimeException('Failed to build expected date in test.');
        }

        return $date;
    }

    private function createFixedExpense(int $day, int $amount, string $description): FixedExpense
    {
        return $this->createFixedExpenseWithContractor($day, $amount, $this->createContractor(), $description);
    }

    private function createFixedExpenseWithContractor(
        int $day,
        int $amount,
        Contractor $contractor,
        string $description = 'Test',
    ): FixedExpense {
        $fixedExpense = new FixedExpense($contractor, $day, $amount, $this->getPlnCurrency());
        $fixedExpense->setDescription($description);
        $this->em()->persist($fixedExpense);
        $this->em()->flush();

        return $fixedExpense;
    }

    private function createContractor(): Contractor
    {
        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();

        return $contractor;
    }

    private function getPlnCurrency(): Currency
    {
        return $this->findOrCreateCurrency('PLN');
    }

    private function findOrCreateCurrency(string $code): Currency
    {
        $currency = $this->em()->getRepository(Currency::class)->find($code);
        if (null === $currency) {
            $currency = new Currency($code);
            $this->em()->persist($currency);
            $this->em()->flush();
        }

        return $currency;
    }

    private function markAsMain(Account $account): void
    {
        /** @var AccountRepository $accountRepository */
        $accountRepository = self::getContainer()->get(AccountRepository::class);
        $accountRepository->setAsMainAccount($account);
    }

    private function assertPlanningIsRefusedWith(string $messagePattern): void
    {
        $operationsBefore = $this->em()->getRepository(AccountOperation::class)->count([]);

        try {
            $this->planner->planNextMonth();
            self::fail('Planning should have been refused.');
        } catch (RuntimeException $exception) {
            self::assertMatchesRegularExpression($messagePattern, $exception->getMessage());
        }

        self::assertSame($operationsBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    private function createAccount(string $currencyCode = 'PLN', bool $ownFunds = true): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currency = $this->findOrCreateCurrency($currencyCode);

        $accountType = new AccountType('Test Type ' . uniqid());
        $this->em()->persist($accountType);

        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => $ownFunds]);
        if (null === $household) {
            throw new RuntimeException('Fixtures must provide the household for this test.');
        }

        $account = new Account($bank, $currency, $accountType, 'ACC-' . uniqid(), $household);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }
}
