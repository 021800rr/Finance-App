<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Bank;
use App\Entity\CurrencyExchangeRate;
use App\Entity\Household;
use App\Service\SummaryService;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use App\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SummaryServiceTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

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

        $currency = $this->currency('PLN');
        $contractor = $this->createContractor();

        // Bank A: holds both our money and the mother's on separate accounts
        $bankA = $this->createBank();
        $ourAccountInBankA = $this->createAccount(
            $currency,
            $bankA,
            $this->createAccountType(),
            $ownHousehold,
            'OUR-A-'.uniqid(),
        );
        $motherAccountInBankA = $this->createAccount(
            $currency,
            $bankA,
            $this->createAccountType(),
            $excludedHousehold,
            'MOTHER-A-'.uniqid(),
        );

        // Bank B: only our money
        $bankB = $this->createBank();
        $ourAccountInBankB = $this->createAccount(
            $currency,
            $bankB,
            $this->createAccountType(),
            $ownHousehold,
            'OUR-B-'.uniqid(),
        );

        // Bank C: only the mother's money
        $bankC = $this->createBank();
        $motherAccountInBankC = $this->createAccount(
            $currency,
            $bankC,
            $this->createAccountType(),
            $excludedHousehold,
            'MOTHER-C-'.uniqid(),
        );

        $this->createOperation($ourAccountInBankA, 100000, contractor: $contractor);
        $this->createOperation($ourAccountInBankB, 50000, contractor: $contractor);
        $this->createOperation($motherAccountInBankA, 9999999, contractor: $contractor);
        $this->createOperation($motherAccountInBankC, 8888888, contractor: $contractor);

        $summaryAfter = $this->summaryService->getSummary();

        $expectedOwnFunds = $ownFundsBefore->add(Money::fromDecimalString('1500.00', 'PLN'));

        self::assertTrue($expectedOwnFunds->equals($summaryAfter['byHousehold'][$ownHousehold->getName()]));
    }

    public function testByHouseholdGroupsAccountsByHouseholdName(): void
    {
        $excludedHousehold = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => false]);
        self::assertNotNull($excludedHousehold, 'Fixtures must provide exactly one non-own-funds household.');

        $bank = $this->createBank();
        $currency = $this->currency('PLN');
        $accountType = $this->createAccountType();
        $contractor = $this->createContractor();

        $account = $this->createAccount($currency, $bank, $accountType, $excludedHousehold, 'ACC-'.uniqid());
        $this->createOperation($account, 60000, contractor: $contractor);

        $summary = $this->summaryService->getSummary();

        self::assertArrayHasKey($excludedHousehold->getName(), $summary['byHousehold']);
    }

    public function testRetirementSumsOnlyAccountsFlaggedAsRetirement(): void
    {
        $ownHousehold = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        self::assertNotNull($ownHousehold, 'Fixtures must provide exactly one own-funds household.');

        $retirementBefore = $this->summaryService->getSummary()['retirementBalanceWithGold'];

        $bank = $this->createBank();
        $currency = $this->currency('PLN');
        $contractor = $this->createContractor();

        $retirementType = $this->createAccountType('Test Retirement '.uniqid(), true);

        $regularType = $this->createAccountType('Test Regular '.uniqid());

        $retirementAccount = $this->createAccount(
            $currency,
            $bank,
            $retirementType,
            $ownHousehold,
            'RET-ACC-'.uniqid(),
        );
        $regularAccount = $this->createAccount($currency, $bank, $regularType, $ownHousehold, 'REG-ACC-'.uniqid());

        $this->createOperation($retirementAccount, 100000, contractor: $contractor);
        $this->createOperation($regularAccount, 500000, contractor: $contractor);

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
        $currency = $this->currency('PLN');
        $contractor = $this->createContractor();
        $sharedType = $this->createAccountType();

        $ourAccount = $this->createAccount($currency, $bank, $sharedType, $ownHousehold, 'OUR-'.uniqid());
        $motherAccount = $this->createAccount($currency, $bank, $sharedType, $excludedHousehold, 'MOTHER-'.uniqid());

        $this->createOperation($ourAccount, 100000, contractor: $contractor);
        $this->createOperation($motherAccount, 9999999, contractor: $contractor);

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
        $currency = $this->currency('PLN');
        $motherOnlyType = $this->createAccountType();

        $account = $this->createAccount($currency, $bank, $motherOnlyType, $excludedHousehold, 'MOTHER-'.uniqid());
        $this->createOperation($account, 60000, contractor: $this->createContractor());

        self::assertArrayNotHasKey($motherOnlyType->getName(), $this->summaryService->getSummary()['byType']);
    }

    public function testRetirementBalanceExcludesTheExcludedHousehold(): void
    {
        $excludedHousehold = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => false]);
        self::assertNotNull($excludedHousehold, 'Fixtures must provide exactly one non-own-funds household.');

        $retirementBefore = $this->summaryService->getSummary()['retirementBalance'];

        $bank = $this->createBank();
        $currency = $this->currency('PLN');
        $retirementType = $this->createAccountType('Test Retirement '.uniqid(), true);

        $motherAccount = $this->createAccount(
            $currency,
            $bank,
            $retirementType,
            $excludedHousehold,
            'MOTHER-RET-'.uniqid(),
        );
        $this->createOperation($motherAccount, 777700, contractor: $this->createContractor());

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
        $ownName = $this->household()->getName();
        $motherName = $this->household(false)->getName();
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
        $currency = $this->currency('CHF');

        $rate = new CurrencyExchangeRate($bank, $currency, new \DateTimeImmutable(), 41234); // 4.1234 PLN za 1 CHF
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

    /**
     * Own: 1000.00 PLN retirement, 200.00 CHF retirement (rate 4.5) -> (900 PLN), 500.00 PLN regular.
     * Gold worth 8500.00 PLN.
     * Mother: 7777.00 PLN retirement, 3333.00 PLN regular.
     */
    private function createMixedHouseholdScenario(): void
    {
        $own = $this->household();
        $mother = $this->household(false);
        $bank = $this->createBank();
        $pln = $this->currency('PLN');
        $chf = $this->currency('CHF');
        $contractor = $this->createContractor();

        $retirementType = $this->createAccountType(retirement: true);
        $regularType = $this->createAccountType();
        $this->createRate($bank, $chf, rateToPln: 45000);

        $this->createOperation(
            $this->createAccount($pln, $bank, $retirementType, $own, 'OWN-RET-PLN-'.uniqid()),
            100000,
            contractor: $contractor,
        );
        $this->createOperation(
            $this->createAccount($chf, $bank, $retirementType, $own, 'OWN-RET-CHF-'.uniqid()),
            20000,
            contractor: $contractor,
        );
        $this->createOperation(
            $this->createAccount($pln, $bank, $regularType, $own, 'OWN-REG-'.uniqid()),
            50000,
            contractor: $contractor,
        );
        $this->createOperation(
            $this->createAccount($pln, $bank, $retirementType, $mother, 'MOTHER-RET-'.uniqid()),
            777700,
            contractor: $contractor,
        );
        $this->createOperation(
            $this->createAccount($pln, $bank, $regularType, $mother, 'MOTHER-REG-'.uniqid()),
            333300,
            contractor: $contractor,
        );

        $this->createGoldPrice($this->createGoldAsset(ounces: 1), pricePln: 850000);
    }
}
