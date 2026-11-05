<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\AccountOperationSummaryService;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use App\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountOperationSummaryServiceTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    private AccountOperationSummaryService $summaryService;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var AccountOperationSummaryService $summaryService */
        $summaryService = self::getContainer()->get(AccountOperationSummaryService::class);
        $this->summaryService = $summaryService;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testSaldoAllSumsAllOperationsRegardlessOfDate(): void
    {
        $account = $this->createAccount();

        $this->createOperation($account, 100000, new \DateTimeImmutable('-5 days'));
        $this->createOperation($account, -20050, new \DateTimeImmutable('today'));
        $this->createOperation($account, 30000, new \DateTimeImmutable('+5 days'));

        $this->em()->flush();

        $summary = $this->summaryService->getBalances($account);

        self::assertTrue(Money::fromDecimalString('1099.50', 'PLN')->equals($summary['saldoAll']));
    }

    public function testSaldoTodayExcludesFutureOperations(): void
    {
        $account = $this->createAccount();

        $this->createOperation($account, 100000, new \DateTimeImmutable('-5 days'));
        $this->createOperation($account, -20050, new \DateTimeImmutable('today'));
        $this->createOperation($account, 30000, new \DateTimeImmutable('+5 days'));

        $this->em()->flush();

        $summary = $this->summaryService->getBalances($account);

        self::assertTrue(Money::fromDecimalString('799.50', 'PLN')->equals($summary['saldoToday']));
    }

    public function testShowSaldoTodayIsFalseWhenNoFutureOperations(): void
    {
        $account = $this->createAccount();

        $this->createOperation($account, 10000, new \DateTimeImmutable('-1 day'));
        $this->createOperation($account, 5000, new \DateTimeImmutable('today'));

        $this->em()->flush();

        $summary = $this->summaryService->getBalances($account);

        self::assertTrue($summary['saldoAll']->equals($summary['saldoToday']));
        self::assertFalse($summary['showSaldoToday']);
    }

    public function testShowSaldoTodayIsTrueWhenFutureOperationExists(): void
    {
        $account = $this->createAccount();

        $this->createOperation($account, 10000, new \DateTimeImmutable('today'));
        $this->createOperation($account, 5000, new \DateTimeImmutable('+3 days'));

        $this->em()->flush();

        $summary = $this->summaryService->getBalances($account);

        self::assertTrue($summary['showSaldoToday']);
    }

    public function testReturnsEmptyResultForAccountWithNoOperations(): void
    {
        $account = $this->createAccount();

        $summary = $this->summaryService->getBalances($account);

        self::assertTrue(Money::zero('PLN')->equals($summary['saldoAll']));
        self::assertTrue(Money::zero('PLN')->equals($summary['saldoToday']));
        self::assertFalse($summary['showSaldoToday']);
    }

    public function testOperationsFromOtherAccountsAreNotIncluded(): void
    {
        $accountA = $this->createAccount();
        $accountB = $this->createAccount();

        $this->createOperation($accountA, 10000, new \DateTimeImmutable('today'));
        $this->createOperation($accountB, 999900, new \DateTimeImmutable('today'));

        $this->em()->flush();

        $summary = $this->summaryService->getBalances($accountA);

        self::assertTrue(Money::fromDecimalString('100.00', 'PLN')->equals($summary['saldoAll']));
    }

    public function testSaldoAllUsesAccountCurrencyForChfAccount(): void
    {
        $account = $this->createAccount(currency: 'CHF');
        $this->createOperation($account, 100000, new \DateTimeImmutable('today'));

        $summary = $this->summaryService->getBalances($account);

        self::assertTrue(Money::fromDecimalString('1000.00', 'CHF')->equals($summary['saldoAll']));
    }
}
