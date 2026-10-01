<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Entity\Household;
use App\Service\SummaryService;
use App\Tests\DatabaseTestTrait;
use App\ValueObject\Money;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SummaryServiceTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private SummaryService $summaryService;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var SummaryService $summaryService */
        $summaryService = self::getContainer()->get(SummaryService::class);
        $this->summaryService = $summaryService;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testOwnFundsHouseholdNeverIncludesExcludedHouseholdMoneyAcrossMultipleBanksAndAccounts(): void
    {
        $ownHousehold = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        self::assertNotNull($ownHousehold, 'Fixtures must provide exactly one own-funds household.');

        $excludedHousehold = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => false]);
        self::assertNotNull($excludedHousehold, 'Fixtures must provide exactly one non-own-funds household.');

        $ownFundsBefore = $this->summaryService->getSummary()['byHousehold'][$ownHousehold->getName()];

        $currency = $this->findOrCreateCurrency('PLN');
        $contractor = $this->createContractor();

        // Bank A: trzyma zarówno nasze, jak i matki pieniądze na osobnych kontach
        $bankA = $this->createBank();
        $ourAccountInBankA = $this->createAccount($bankA, $currency, $this->createAccountType(), $ownHousehold, 'OUR-A-' . uniqid());
        $motherAccountInBankA = $this->createAccount($bankA, $currency, $this->createAccountType(), $excludedHousehold, 'MOTHER-A-' . uniqid());

        // Bank B: tylko nasze pieniądze
        $bankB = $this->createBank();
        $ourAccountInBankB = $this->createAccount($bankB, $currency, $this->createAccountType(), $ownHousehold, 'OUR-B-' . uniqid());

        // Bank C: tylko pieniądze matki
        $bankC = $this->createBank();
        $motherAccountInBankC = $this->createAccount($bankC, $currency, $this->createAccountType(), $excludedHousehold, 'MOTHER-C-' . uniqid());

        $this->createOperation($ourAccountInBankA, $contractor, $currency, 100000);
        $this->createOperation($ourAccountInBankB, $contractor, $currency, 50000);
        $this->createOperation($motherAccountInBankA, $contractor, $currency, 9999999);
        $this->createOperation($motherAccountInBankC, $contractor, $currency, 8888888);

        $summaryAfter = $this->summaryService->getSummary();

        $expectedOwnFunds = $ownFundsBefore->add(Money::fromDecimalString('1500.00', 'PLN'));

        self::assertTrue($expectedOwnFunds->equals($summaryAfter['byHousehold'][$ownHousehold->getName()]));
    }

    public function testByHouseholdGroupsAccountsByHouseholdName(): void
    {
        $excludedHousehold = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => false]);
        self::assertNotNull($excludedHousehold, 'Fixtures must provide exactly one non-own-funds household.');

        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('PLN');
        $accountType = $this->createAccountType();
        $contractor = $this->createContractor();

        $account = $this->createAccount($bank, $currency, $accountType, $excludedHousehold, 'ACC-' . uniqid());
        $this->createOperation($account, $contractor, $currency, 60000);

        $summary = $this->summaryService->getSummary();

        self::assertArrayHasKey($excludedHousehold->getName(), $summary['byHousehold']);
    }

    public function testRetirementSumsOnlyAccountsFlaggedAsRetirement(): void
    {
        $ownHousehold = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        self::assertNotNull($ownHousehold, 'Fixtures must provide exactly one own-funds household.');

        $retirementBefore = $this->summaryService->getSummary()['retirementBalanceWithGold'];

        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('PLN');
        $contractor = $this->createContractor();

        $retirementType = new AccountType('Test Retirement ' . uniqid(), true);
        $this->em()->persist($retirementType);

        $regularType = new AccountType('Test Regular ' . uniqid());
        $this->em()->persist($regularType);

        $retirementAccount = $this->createAccount($bank, $currency, $retirementType, $ownHousehold, 'RET-ACC-' . uniqid());
        $regularAccount = $this->createAccount($bank, $currency, $regularType, $ownHousehold, 'REG-ACC-' . uniqid());

        $this->createOperation($retirementAccount, $contractor, $currency, 100000);
        $this->createOperation($regularAccount, $contractor, $currency, 500000);

        $summaryAfter = $this->summaryService->getSummary();

        $expectedRetirement = $retirementBefore->add(Money::fromDecimalString('1000.00', 'PLN'));

        self::assertTrue($expectedRetirement->equals($summaryAfter['retirementBalanceWithGold']));
    }

    public function testRatesByBankReturnsMoneyAtRateScale(): void
    {
        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('CHF');

        $rate = new CurrencyExchangeRate($bank, $currency, new DateTimeImmutable(), 41234); // 4.1234 PLN za 1 CHF
        $this->em()->persist($rate);
        $this->em()->flush();

        $summary = $this->summaryService->getSummary();

        self::assertArrayHasKey($bank->getName(), $summary['ratesByBank']);
        self::assertArrayHasKey('CHF', $summary['ratesByBank'][$bank->getName()]);

        $money = $summary['ratesByBank'][$bank->getName()]['CHF'];

        self::assertInstanceOf(Money::class, $money);
        self::assertSame(4, $money->getScale());
        self::assertSame(41234, $money->getMinorUnits());
        self::assertSame('4.1234', $money->toDecimalString());
    }

    private function createBank(): Bank
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        return $bank;
    }

    private function createAccountType(): AccountType
    {
        $accountType = new AccountType('Test Type ' . uniqid());
        $this->em()->persist($accountType);

        return $accountType;
    }

    private function createContractor(): Contractor
    {
        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);

        return $contractor;
    }

    private function findOrCreateCurrency(string $code): Currency
    {
        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->findOneBy(['code' => $code]);
        if (null === $currency) {
            $currency = new Currency($code);
            $this->em()->persist($currency);
        }

        return $currency;
    }

    private function createAccount(Bank $bank, Currency $currency, AccountType $type, Household $household, string $accountNumber): Account
    {
        $account = new Account($bank, $currency, $type, $accountNumber, $household);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }

    private function createOperation(Account $account, Contractor $contractor, Currency $currency, int $amount): AccountOperation
    {
        $operation = new AccountOperation($account, $contractor, new DateTimeImmutable(), $amount, $currency);
        $this->em()->persist($operation);
        $this->em()->flush();

        return $operation;
    }
}
