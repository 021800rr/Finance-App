<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Constant\AccountTypeNames;
use App\Constant\Contractors;
use App\Constant\Currencies;
use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\GoldForm;
use App\Entity\Household;
use App\Entity\User;
use App\Service\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public const string REF_USER = 'user';
    public const string REF_CONTRACTOR_SELF_TRANSFER = 'contractor-self-transfer';
    public const string REF_CURRENCY_PLN = 'currency-pln';
    public const string REF_CURRENCY_CHF = 'currency-chf';
    public const string REF_BANK_1 = 'bank-1';
    public const string REF_BANK_4 = 'bank-4';
    public const string REF_ACCOUNT_BANK1_ROR_PLN = 'account-bank1-ror-pln';
    public const string REF_ACCOUNT_BANK1_WAK_PLN = 'account-bank1-wak-pln';
    public const string REF_ACCOUNT_BANK1_WAK_CHF = 'account-bank1-wak-chf';
    public const string REF_ACCOUNT_BANK2_MATKA_ROR = 'account-bank2-matka-ror';
    public const string REF_ACCOUNT_BANK2_MATKA_OSZCZEDNOSCI = 'account-bank2-matka-oszczednosci';
    public const string REF_ACCOUNT_BANK3_MATKA = 'account-bank3-matka';
    public const string REF_ACCOUNT_BANK4_ROR_PLN = 'account-bank4-ror-pln';
    public const string REF_ACCOUNT_BANK4_EME_CHF = 'account-bank4-eme-chf';
    public const string REF_GOLD_FORM_SZTABKA = 'gold-form-sztabka';

    public function __construct(private readonly UserFactory $userFactory)
    {
    }

    public function load(ObjectManager $manager): void
    {
        /* USER */
        $user = $this->user($manager, 'test@example.com', 'test1234');
        $this->addReference(self::REF_USER, $user);

        /* CONTRACTORS */
        $contractor = $this->contractor($manager);
        $this->addReference(self::REF_CONTRACTOR_SELF_TRANSFER, $contractor);

        /* CURRENCIES */
        $pln = $this->currency($manager, Currencies::SETTLEMENT_CURRENCY_CODE);
        $chf = $this->currency($manager, 'CHF');
        $this->addReference(self::REF_CURRENCY_PLN, $pln);
        $this->addReference(self::REF_CURRENCY_CHF, $chf);

        /* ACCOUNT TYPES */
        $ror = $this->accountType($manager, AccountTypeNames::ROR);
        $wakacje = $this->accountType($manager, AccountTypeNames::WAKACJE);
        $emerytura = $this->accountType($manager, 'Emerytura', true);
        $oszczednosci = $this->accountType($manager, 'Oszczędności');

        /** BANKS */
        $bank1 = $this->bank($manager, 'Bank1');
        $bank2 = $this->bank($manager, 'Bank2');
        $bank3 = $this->bank($manager, 'Bank3');
        $bank4 = $this->bank($manager, 'Bank4');
        $this->addReference(self::REF_BANK_1, $bank1);
        $this->addReference(self::REF_BANK_4, $bank4);

        /* HOUSEHOLDS */
        $nasze = $this->household($manager, 'Nasze', true);
        $matka = $this->household($manager, 'Matka', false);

        /* ACCOUNTS */
        $accountBank1RorPln = $this->account($manager, $bank1, $pln, $ror, $nasze, 'Bank1-ROR-PLN', isMain: true);
        $accountBank1WakPln = $this->account($manager, $bank1, $pln, $wakacje, $nasze, 'Bank1-WAK-PLN');
        $accountBank1WakChf = $this->account($manager, $bank1, $chf, $wakacje, $nasze, 'Bank1-WAK-CHF');
        $this->addReference(self::REF_ACCOUNT_BANK1_ROR_PLN, $accountBank1RorPln);
        $this->addReference(self::REF_ACCOUNT_BANK1_WAK_PLN, $accountBank1WakPln);
        $this->addReference(self::REF_ACCOUNT_BANK1_WAK_CHF, $accountBank1WakChf);

        $accountBank2MatkaRor = $this->account($manager, $bank2, $pln, $ror, $matka, 'Bank2-MATKA-ROR');
        $accountBank2MatkaOszczednosci = $this->account($manager, $bank2, $pln, $oszczednosci, $matka, 'Bank2-MATKA-OSZCZĘDNOŚCI');
        $this->addReference(self::REF_ACCOUNT_BANK2_MATKA_ROR, $accountBank2MatkaRor);
        $this->addReference(self::REF_ACCOUNT_BANK2_MATKA_OSZCZEDNOSCI, $accountBank2MatkaOszczednosci);

        $accountBank3Matka = $this->account($manager, $bank3, $pln, $ror, $matka, 'Bank3-MATKA');
        $this->addReference(self::REF_ACCOUNT_BANK3_MATKA, $accountBank3Matka);

        $accountBank4RorPln = $this->account($manager, $bank4, $pln, $emerytura, $nasze, 'Bank4-ROR-PLN');
        $accountBank4EmeChf = $this->account($manager, $bank4, $chf, $emerytura, $nasze, 'Bank4-EME-CHF');
        $this->addReference(self::REF_ACCOUNT_BANK4_ROR_PLN, $accountBank4RorPln);
        $this->addReference(self::REF_ACCOUNT_BANK4_EME_CHF, $accountBank4EmeChf);

        /* GOLD FORMS */
        $this->goldForm($manager, 'moneta');
        $goldFormSztabka = $this->goldForm($manager, 'sztabka');
        $this->addReference(self::REF_GOLD_FORM_SZTABKA, $goldFormSztabka);

        $manager->flush();
    }

    private function user(ObjectManager $manager, string $email, string $plainPassword): User
    {
        $user = $this->userFactory->create($email, $plainPassword);
        $manager->persist($user);

        return $user;
    }

    private function currency(ObjectManager $manager, string $code): Currency
    {
        $currency = new Currency($code);
        $manager->persist($currency);

        return $currency;
    }

    private function accountType(ObjectManager $manager, string $name, bool $isRetirement = false): AccountType
    {
        $accountType = new AccountType($name, $isRetirement);
        $manager->persist($accountType);

        return $accountType;
    }

    private function bank(ObjectManager $manager, string $name): Bank
    {
        $bank = new Bank($name);
        $manager->persist($bank);

        return $bank;
    }

    private function household(ObjectManager $manager, string $name, bool $isOwnFunds): Household
    {
        $household = new Household($name, $isOwnFunds);
        $manager->persist($household);

        return $household;
    }

    private function account(
        ObjectManager $manager,
        Bank $bank,
        Currency $currency,
        AccountType $type,
        Household $household,
        string $number,
        bool $isMain = false,
    ): Account {
        $account = new Account($bank, $currency, $type, $number, $household);
        $account->setMain($isMain);
        $manager->persist($account);

        return $account;
    }

    private function goldForm(ObjectManager $manager, string $name): GoldForm
    {
        $goldForm = new GoldForm($name);
        $manager->persist($goldForm);

        return $goldForm;
    }

    private function contractor(ObjectManager $manager): Contractor
    {
        // Also seeded by migrations/Version202609210220.php so it exists on
        // every environment, including production where fixtures never run.
        $contractor = new Contractor(Contractors::SELF_TRANSFER_NAME);
        $manager->persist($contractor);

        return $contractor;
    }
}
