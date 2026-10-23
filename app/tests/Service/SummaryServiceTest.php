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
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
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

        // Bank A: holds both our money and the mother's on separate accounts
        $bankA = $this->createBank();
        $ourAccountInBankA = $this->createAccount($bankA, $currency, $this->createAccountType(), $ownHousehold, 'OUR-A-' . uniqid());
        $motherAccountInBankA = $this->createAccount($bankA, $currency, $this->createAccountType(), $excludedHousehold, 'MOTHER-A-' . uniqid());

        // Bank B: only our money
        $bankB = $this->createBank();
        $ourAccountInBankB = $this->createAccount($bankB, $currency, $this->createAccountType(), $ownHousehold, 'OUR-B-' . uniqid());

        // Bank C: only the mother's money
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

    public function testByTypeSumsOnlyOwnFundsAccountsEvenWhenTheMotherUsesTheSameAccountType(): void
    {
        $ownHousehold = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        self::assertNotNull($ownHousehold, 'Fixtures must provide exactly one own-funds household.');

        $excludedHousehold = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => false]);
        self::assertNotNull($excludedHousehold, 'Fixtures must provide exactly one non-own-funds household.');

        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('PLN');
        $contractor = $this->createContractor();
        $sharedType = $this->createAccountType();

        $ourAccount = $this->createAccount($bank, $currency, $sharedType, $ownHousehold, 'OUR-' . uniqid());
        $motherAccount = $this->createAccount($bank, $currency, $sharedType, $excludedHousehold, 'MOTHER-' . uniqid());

        $this->createOperation($ourAccount, $contractor, $currency, 100000);
        $this->createOperation($motherAccount, $contractor, $currency, 9999999);

        $byType = $this->summaryService->getSummary()['byType'];

        self::assertArrayHasKey($sharedType->getName(), $byType);
        self::assertSame('1000.00', $byType[$sharedType->getName()]['pln']->toDecimalString());
        self::assertSame('1000.00', $byType[$sharedType->getName()]['raw']['PLN']->toDecimalString());
    }

    public function testByTypeOmitsTypesUsedOnlyByTheExcludedHousehold(): void
    {
        $excludedHousehold = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => false]);
        self::assertNotNull($excludedHousehold, 'Fixtures must provide exactly one non-own-funds household.');

        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('PLN');
        $motherOnlyType = $this->createAccountType();

        $account = $this->createAccount($bank, $currency, $motherOnlyType, $excludedHousehold, 'MOTHER-' . uniqid());
        $this->createOperation($account, $this->createContractor(), $currency, 60000);

        self::assertArrayNotHasKey($motherOnlyType->getName(), $this->summaryService->getSummary()['byType']);
    }

    public function testRetirementBalanceExcludesTheExcludedHousehold(): void
    {
        $excludedHousehold = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => false]);
        self::assertNotNull($excludedHousehold, 'Fixtures must provide exactly one non-own-funds household.');

        $retirementBefore = $this->summaryService->getSummary()['retirementBalance'];

        $bank = $this->createBank();
        $currency = $this->findOrCreateCurrency('PLN');
        $retirementType = new AccountType('Test Retirement ' . uniqid(), true);
        $this->em()->persist($retirementType);

        $motherAccount = $this->createAccount($bank, $currency, $retirementType, $excludedHousehold, 'MOTHER-RET-' . uniqid());
        $this->createOperation($motherAccount, $this->createContractor(), $currency, 777700);

        self::assertTrue($retirementBefore->equals($this->summaryService->getSummary()['retirementBalance']));
    }

    public function testCacheGoldAndBackupAreCalculatedFromOwnFundsOnly(): void
    {
        $before = $this->summaryService->getSummary();

        $this->createMixedHouseholdScenario();
        // Own: 1000.00 PLN retirement, 200.00 CHF retirement (rate 4.5) -> (900 PLN), 500.00 PLN regular.
        // Gold worth 8500.00 PLN.
        // Mother: 7777.00 PLN retirement, 3333.00 PLN regular.

        $after = $this->summaryService->getSummary();

        // Kasa: own retirement accounts only, CHF converted at the bank's rate: 1000.00 PLN + 200.00 CHF * 4.5.
        self::assertSame('1900.00', $this->delta($after['retirementBalance'], $before['retirementBalance']));
        // Gold ("Złoto"): the latest price of the new asset.
        self::assertSame('8500.00', $this->delta($after['gold'], $before['gold']));
        // Backup: Kasa + Gold.
        self::assertSame(
            '10400.00',
            $this->delta($after['retirementBalanceWithGold'], $before['retirementBalanceWithGold'])
        );
        self::assertTrue(
            $after['retirementBalance']->add($after['gold'])->equals($after['retirementBalanceWithGold'])
        );
    }

    public function testHouseholdTotalsAddUpOwnAccountsAndGoldForOurAndOnlyAccountsForTheMother(): void
    {
        $ownName = $this->ownHousehold()->getName();
        $motherName = $this->excludedHousehold()->getName();
        $before = $this->summaryService->getSummary()['byHousehold'];

        $this->createMixedHouseholdScenario();

        $after = $this->summaryService->getSummary()['byHousehold'];

        // Nasze: 1000.00 + 200.00 CHF * 4.5 (= 900.00) + 500.00 regular + 8500.00 gold.
        self::assertSame('10900.00', $this->delta($after[$ownName], $before[$ownName]));
        // Matka: 7777.00 + 3333.00, and no gold.
        self::assertSame('11110.00', $this->delta($after[$motherName], $before[$motherName]));
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

    private function delta(Money $after, Money $before): string
    {
        return $after->add($before->negate())->toDecimalString();
    }

    private function ownHousehold(): Household
    {
        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        self::assertNotNull($household, 'Fixtures must provide exactly one own-funds household.');

        return $household;
    }

    private function excludedHousehold(): Household
    {
        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => false]);
        self::assertNotNull($household, 'Fixtures must provide exactly one non-own-funds household.');

        return $household;
    }

    /**
     * Own: 1000.00 PLN retirement, 200.00 CHF retirement (rate 4.5) -> (900 PLN), 500.00 PLN regular.
     * Gold worth 8500.00 PLN.
     * Mother: 7777.00 PLN retirement, 3333.00 PLN regular.
     */
    private function createMixedHouseholdScenario(): void
    {
        $own = $this->ownHousehold();
        $mother = $this->excludedHousehold();
        $bank = $this->createBank();
        $pln = $this->findOrCreateCurrency('PLN');
        $chf = $this->findOrCreateCurrency('CHF');
        $contractor = $this->createContractor();

        $retirementType = new AccountType('Test Retirement ' . uniqid(), true);
        $this->em()->persist($retirementType);
        $regularType = $this->createAccountType();

        $this->em()->persist(new CurrencyExchangeRate($bank, $chf, new DateTimeImmutable(), 45000));

        $this->createOperation($this->createAccount($bank, $pln, $retirementType, $own, 'OWN-RET-PLN-' . uniqid()), $contractor, $pln, 100000);
        $this->createOperation($this->createAccount($bank, $chf, $retirementType, $own, 'OWN-RET-CHF-' . uniqid()), $contractor, $chf, 20000);
        $this->createOperation($this->createAccount($bank, $pln, $regularType, $own, 'OWN-REG-' . uniqid()), $contractor, $pln, 50000);
        $this->createOperation($this->createAccount($bank, $pln, $retirementType, $mother, 'MOTHER-RET-' . uniqid()), $contractor, $pln, 777700);
        $this->createOperation($this->createAccount($bank, $pln, $regularType, $mother, 'MOTHER-REG-' . uniqid()), $contractor, $pln, 333300);

        $goldForm = new GoldForm('test-form-' . uniqid());
        $this->em()->persist($goldForm);
        $goldAsset = new GoldAsset($goldForm, 1, 'Test gold ' . uniqid());
        $this->em()->persist($goldAsset);
        $this->em()->persist(new GoldPrice($goldAsset, new DateTimeImmutable(), 850000, $pln));
        $this->em()->flush();
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
