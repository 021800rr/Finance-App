<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\Repository\AccountBalanceReportRepository;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountBalanceReportRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

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

        $operation = new AccountOperation($account, $contractor, new DateTimeImmutable(), 25050, $account->getCurrency());
        $this->em()->persist($operation);
        $this->em()->flush();

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

        $incoming = new AccountOperation($account, $contractor, new DateTimeImmutable(), 100000, $account->getCurrency());
        $this->em()->persist($incoming);

        $outgoing = new AccountOperation($account, $contractor, new DateTimeImmutable(), -30025, $account->getCurrency());
        $this->em()->persist($outgoing);

        $this->em()->flush();

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
     * @return array{id: int, bank: string, type: string, currency: string, account_number: string, hidden: bool, household: string, is_own_funds: bool, is_retirement: bool, saldo: string}|null
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

    private function createAccount(): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currency = $this->findOrCreateCurrency('PLN');

        $accountType = new AccountType('Test Type ' . uniqid());
        $this->em()->persist($accountType);

        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        if (null === $household) {
            throw new \RuntimeException('Fixtures must provide exactly one own-funds household.');
        }

        $account = new Account($bank, $currency, $accountType, 'ACC-' . uniqid(), $household);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }

    private function createContractor(): Contractor
    {
        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();

        return $contractor;
    }

    private function findOrCreateCurrency(string $code): Currency
    {
        $repository = $this->em()->getRepository(Currency::class);
        $currency = $repository->findOneBy(['code' => $code]);
        if (null === $currency) {
            $currency = new Currency($code);
            $this->em()->persist($currency);
        }

        return $currency;
    }
}
