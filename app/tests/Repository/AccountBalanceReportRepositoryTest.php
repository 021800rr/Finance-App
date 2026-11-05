<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\Household;
use App\Repository\AccountBalanceReportRepository;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountBalanceReportRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    private AccountBalanceReportRepository $reportRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var AccountBalanceReportRepository $reportRepository */
        $reportRepository = self::getContainer()->get(AccountBalanceReportRepository::class);
        $this->reportRepository = $reportRepository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testFetchAccountBalancesReturnsExpectedShapeAndValues(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor();

        $this->createOperation($account, 25050, contractor: $contractor);

        $row = $this->findBalanceRow($account->getAccountNumber());

        self::assertNotNull($row, 'Expected to find the freshly created account in fetchAccountBalances() result.');
        self::assertSame($account->getBank()->getName(), $row['bank']);
        self::assertSame($account->getType()->getName(), $row['type']);
        self::assertSame('PLN', $row['currency']);
        self::assertFalse($row['hidden']);
        self::assertSame($account->getHousehold()->getName(), $row['household']);
        self::assertTrue($row['is_own_funds']);
        self::assertSame('25050', $row['saldo']);
    }

    public function testFetchAccountBalancesSumsMultipleOperations(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor();

        $this->createOperation($account, 100000, contractor: $contractor);
        $this->createOperation($account, -30025, contractor: $contractor);

        $row = $this->findBalanceRow($account->getAccountNumber());

        self::assertNotNull($row);
        self::assertSame('69975', $row['saldo']);
    }

    public function testFetchAccountBalancesReturnsZeroSaldoForAccountWithNoOperations(): void
    {
        $account = $this->createAccount();

        $row = $this->findBalanceRow($account->getAccountNumber());

        self::assertNotNull($row);
        self::assertSame('0', $row['saldo']);
    }

    /**
     * @return array{
     *     id: int,
     *     bank: string,
     *     type: string,
     *     currency: string,
     *     account_number: string,
     *     hidden: bool,
     *     household: string,
     *     is_own_funds: bool,
     *     is_retirement: bool,
     *     saldo: string
     * }|null
     */
    private function findBalanceRow(string $accountNumber): ?array
    {
        $balances = $this->reportRepository->fetchAccountBalances();

        foreach ($balances as $candidate) {
            if ($candidate['account_number'] === $accountNumber) {
                return $candidate;
            }
        }

        return null;
    }
}
