<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Entity\FixedExpense;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Service\TransactionService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Example account operations, exchange rate history, gold price history and
 * fixed expenses, built on top of the entities created by AppFixtures.
 *
 * Demo data for the dev database only: `make test` also runs
 * `doctrine:fixtures:load --env=test` to seed the baseline the test suite
 * expects, and several tests assert on an empty/known state for these
 * entity types, so this fixture is a no-op in the test environment.
 */
class OperationFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
        private readonly TransactionService $transactionService,
    ) {
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        if ('test' === $this->environment) {
            return;
        }

        /** @var Account $accountRorPln */
        $accountRorPln = $this->getReference(AppFixtures::REF_ACCOUNT_BANK1_ROR_PLN, Account::class);
        /** @var Account $accountWakPln */
        $accountWakPln = $this->getReference(AppFixtures::REF_ACCOUNT_BANK1_WAK_PLN, Account::class);
        /** @var Account $accountWakChf */
        $accountWakChf = $this->getReference(AppFixtures::REF_ACCOUNT_BANK1_WAK_CHF, Account::class);
        /** @var Account $accountBank2MatkaRor */
        $accountBank2MatkaRor = $this->getReference(AppFixtures::REF_ACCOUNT_BANK2_MATKA_ROR, Account::class);
        /** @var Account $accountBank2MatkaOszczednosci */
        $accountBank2MatkaOszczednosci = $this->getReference(AppFixtures::REF_ACCOUNT_BANK2_MATKA_OSZCZEDNOSCI, Account::class);
        /** @var Account $accountBank3Matka */
        $accountBank3Matka = $this->getReference(AppFixtures::REF_ACCOUNT_BANK3_MATKA, Account::class);
        /** @var Account $accountBank4RorPln */
        $accountBank4RorPln = $this->getReference(AppFixtures::REF_ACCOUNT_BANK4_ROR_PLN, Account::class);
        /** @var Account $accountBank4EmeChf */
        $accountBank4EmeChf = $this->getReference(AppFixtures::REF_ACCOUNT_BANK4_EME_CHF, Account::class);
        /** @var Currency $pln */
        $pln = $this->getReference(AppFixtures::REF_CURRENCY_PLN, Currency::class);
        /** @var Currency $chf */
        $chf = $this->getReference(AppFixtures::REF_CURRENCY_CHF, Currency::class);
        /** @var Bank $bank1 */
        $bank1 = $this->getReference(AppFixtures::REF_BANK_1, Bank::class);
        /** @var Bank $bank4 */
        $bank4 = $this->getReference(AppFixtures::REF_BANK_4, Bank::class);
        /** @var GoldForm $goldFormSztabka */
        $goldFormSztabka = $this->getReference(AppFixtures::REF_GOLD_FORM_SZTABKA, GoldForm::class);
        /** @var Contractor $selfTransfer */
        $selfTransfer = $this->getReference(AppFixtures::REF_CONTRACTOR_SELF_TRANSFER, Contractor::class);

        /* CONTRACTORS */
        $employer = $this->contractor($manager, 'Pracodawca');
        $shop = $this->contractor($manager, 'Biedronka');
        $fuel = $this->contractor($manager, 'Orlen');
        $netflix = $this->contractor($manager, 'Netflix');
        $landlord = $this->contractor($manager, 'Właściciel');
        $tenant = $this->contractor($manager, 'Najemca');
        $housingCommunity = $this->contractor($manager, 'Wspólnota mieszkaniowa');
        $inheritance = $this->contractor($manager, 'Spadek');

        /* ACCOUNT OPERATIONS — three months of a typical household, amounts in minor units (grosze) */
        $this->operation($manager, $accountRorPln, $employer, '2026-07-10', 500000, $pln, 'Wypłata');
        $this->operation($manager, $accountRorPln, $shop, '2026-07-12', -15000, $pln, 'Zakupy spożywcze');
        $this->operation($manager, $accountRorPln, $fuel, '2026-07-20', -25000, $pln, 'Paliwo');

        $this->operation($manager, $accountRorPln, $employer, '2026-08-10', 500000, $pln, 'Wypłata');
        $this->operation($manager, $accountRorPln, $shop, '2026-08-14', -18000, $pln, 'Zakupy spożywcze');
        $this->operation($manager, $accountRorPln, $netflix, '2026-08-22', -5500, $pln, 'Subskrypcja');

        $this->operation($manager, $accountRorPln, $employer, '2026-09-10', 510000, $pln, 'Wypłata');
        $this->operation($manager, $accountRorPln, $shop, '2026-09-12', -16500, $pln, 'Zakupy spożywcze');
        $this->operation($manager, $accountRorPln, $fuel, '2026-09-18', -27000, $pln, 'Paliwo');

        /* MATKA — najem mieszkania (Bank2) + drugie konto (Bank3) */
        $this->operation($manager, $accountBank3Matka, $inheritance, '2026-06-01', 500000, $pln, 'Spadek');
        $this->operation($manager, $accountBank2MatkaRor, $tenant, '2026-07-05', 180000, $pln, 'Czynsz najmu');
        $this->operation($manager, $accountBank2MatkaRor, $tenant, '2026-08-05', 180000, $pln, 'Czynsz najmu');
        $this->operation($manager, $accountBank2MatkaRor, $tenant, '2026-09-05', 180000, $pln, 'Czynsz najmu');
        $this->operation($manager, $accountBank3Matka, $housingCommunity, '2026-07-10', -45000, $pln, 'Czynsz administracyjny');
        $this->operation($manager, $accountBank3Matka, $housingCommunity, '2026-08-10', -45000, $pln, 'Czynsz administracyjny');
        $this->operation($manager, $accountBank3Matka, $housingCommunity, '2026-09-10', -45000, $pln, 'Czynsz administracyjny');

        $manager->flush();

        // Real transfers via App\Service\TransactionService, matching the app's own transfer
        // rules: amounts given explicitly on both sides, no +/- signs.

        // Wakacje PLN — same-currency transfer, amountTo not needed.
        $this->transactionService->transferBetweenAccounts($accountRorPln, $accountWakPln, $selfTransfer, new \DateTimeImmutable('2026-07-01'), '300.00', null, 'Przelew oszczędności na wakacje (PLN)');
        $this->transactionService->transferBetweenAccounts($accountRorPln, $accountWakPln, $selfTransfer, new \DateTimeImmutable('2026-08-01'), '300.00', null, 'Przelew oszczędności na wakacje (PLN)');
        $this->transactionService->transferBetweenAccounts($accountRorPln, $accountWakPln, $selfTransfer, new \DateTimeImmutable('2026-09-01'), '300.00', null, 'Przelew oszczędności na wakacje (PLN)');

        // Wakacje CHF — different-currency transfer, amounts on both sides, roughly at the CHF rate of the month.
        $this->transactionService->transferBetweenAccounts($accountRorPln, $accountWakChf, $selfTransfer, new \DateTimeImmutable('2026-07-01'), '2225.00', '500.00', 'Przelew oszczędności na wakacje');
        $this->transactionService->transferBetweenAccounts($accountRorPln, $accountWakChf, $selfTransfer, new \DateTimeImmutable('2026-08-01'), '2260.00', '500.00', 'Przelew oszczędności na wakacje');
        $this->transactionService->transferBetweenAccounts($accountRorPln, $accountWakChf, $selfTransfer, new \DateTimeImmutable('2026-09-01'), '2250.00', '500.00', 'Przelew oszczędności na wakacje');

        // Emerytura — składka miesięczna z konta głównego (PLN, ta sama waluta).
        // Wyższa niż kwota wymieniana na CHF poniżej, żeby konto nigdy nie zszedło pod zero.
        $this->transactionService->transferBetweenAccounts($accountRorPln, $accountBank4RorPln, $selfTransfer, new \DateTimeImmutable('2026-07-01'), '500.00', null, 'Składka emerytalna');
        $this->transactionService->transferBetweenAccounts($accountRorPln, $accountBank4RorPln, $selfTransfer, new \DateTimeImmutable('2026-08-01'), '500.00', null, 'Składka emerytalna');
        $this->transactionService->transferBetweenAccounts($accountRorPln, $accountBank4RorPln, $selfTransfer, new \DateTimeImmutable('2026-09-01'), '500.00', null, 'Składka emerytalna');

        // Emerytura — część składki wymieniana na CHF (Bank4-ROR-PLN -> Bank4-EME-CHF).
        $this->transactionService->transferBetweenAccounts($accountBank4RorPln, $accountBank4EmeChf, $selfTransfer, new \DateTimeImmutable('2026-07-15'), '445.00', '100.00', 'Zakup CHF na emeryturę');
        $this->transactionService->transferBetweenAccounts($accountBank4RorPln, $accountBank4EmeChf, $selfTransfer, new \DateTimeImmutable('2026-08-15'), '452.00', '100.00', 'Zakup CHF na emeryturę');
        $this->transactionService->transferBetweenAccounts($accountBank4RorPln, $accountBank4EmeChf, $selfTransfer, new \DateTimeImmutable('2026-09-15'), '450.00', '100.00', 'Zakup CHF na emeryturę');

        // Matka — miesięczny transfer nadwyżki z najmu na oszczędności (Bank2, ta sama waluta).
        $this->transactionService->transferBetweenAccounts($accountBank2MatkaRor, $accountBank2MatkaOszczednosci, $selfTransfer, new \DateTimeImmutable('2026-07-06'), '500.00', null, 'Przelew nadwyżki z najmu');
        $this->transactionService->transferBetweenAccounts($accountBank2MatkaRor, $accountBank2MatkaOszczednosci, $selfTransfer, new \DateTimeImmutable('2026-08-06'), '500.00', null, 'Przelew nadwyżki z najmu');
        $this->transactionService->transferBetweenAccounts($accountBank2MatkaRor, $accountBank2MatkaOszczednosci, $selfTransfer, new \DateTimeImmutable('2026-09-06'), '500.00', null, 'Przelew nadwyżki z najmu');

        /* CURRENCY EXCHANGE RATE HISTORY (rate to PLN, scale 4) */
        $this->exchangeRate($manager, $bank1, $chf, '2026-07-01', 44500);
        $this->exchangeRate($manager, $bank1, $chf, '2026-08-01', 45200);
        $this->exchangeRate($manager, $bank1, $chf, '2026-09-15', 45000);

        $this->exchangeRate($manager, $bank4, $chf, '2026-07-15', 44500);
        $this->exchangeRate($manager, $bank4, $chf, '2026-08-15', 45200);
        $this->exchangeRate($manager, $bank4, $chf, '2026-09-15', 45000);

        /* GOLD ASSET + PRICE HISTORY (ounces scale 4 -> 1.0000 oz, price scale 2) */
        $goldAsset = new GoldAsset($goldFormSztabka, 10000, 'Sztabka 1oz');
        $manager->persist($goldAsset);

        $this->goldPrice($manager, $goldAsset, '2026-07-01', 800000, $pln);
        $this->goldPrice($manager, $goldAsset, '2026-08-01', 830000, $pln);
        $this->goldPrice($manager, $goldAsset, '2026-09-15', 850000, $pln);

        /* FIXED EXPENSES (amounts always negative, minor units) */
        $this->fixedExpense($manager, $landlord, 10, -320000, $pln, 'Czynsz');
        $this->fixedExpense($manager, $netflix, 5, -5500, $pln, 'Netflix');

        $manager->flush();
    }

    private function contractor(ObjectManager $manager, string $name): Contractor
    {
        $contractor = new Contractor($name);
        $manager->persist($contractor);

        return $contractor;
    }

    private function operation(
        ObjectManager $manager,
        Account $account,
        Contractor $contractor,
        string $date,
        int $amount,
        Currency $currency,
        string $description,
    ): void {
        $operation = new AccountOperation($account, $contractor, new \DateTimeImmutable($date), $amount, $currency);
        $operation->setDescription($description);
        $operation->setConfirmed(true);
        $manager->persist($operation);
    }

    private function exchangeRate(ObjectManager $manager, Bank $bank, Currency $currency, string $date, int $rateToPln): void
    {
        $rate = new CurrencyExchangeRate($bank, $currency, new \DateTimeImmutable($date), $rateToPln);
        $manager->persist($rate);
    }

    private function goldPrice(ObjectManager $manager, GoldAsset $asset, string $date, int $pricePln, Currency $currency): void
    {
        $price = new GoldPrice($asset, new \DateTimeImmutable($date), $pricePln, $currency);
        $manager->persist($price);
    }

    private function fixedExpense(ObjectManager $manager, Contractor $contractor, int $day, int $amount, Currency $currency, string $description): void
    {
        $fixedExpense = new FixedExpense($contractor, $day, $amount, $currency);
        $fixedExpense->setDescription($description);
        $manager->persist($fixedExpense);
    }
}
