<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\Household;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    public function testSetAccountNumberAcceptsNonBlankValue(): void
    {
        $account = $this->createAccount();

        $account->setAccountNumber('ACC-12345');

        self::assertSame('ACC-12345', $account->getAccountNumber());
    }

    public function testSetAccountNumberRejectsBlankValue(): void
    {
        $account = $this->createAccount();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account number cannot be blank.');

        $account->setAccountNumber('   ');
    }

    public function testConstructorSetsAllRequiredFields(): void
    {
        $bank = new Bank('Test Bank');
        $currency = new Currency('PLN');
        $type = new AccountType('Test Type');
        $household = new Household('Test Household');

        $account = new Account($bank, $currency, $type, 'ACC-12345', $household);

        self::assertSame($bank, $account->getBank());
        self::assertSame($currency, $account->getCurrency());
        self::assertSame($type, $account->getType());
        self::assertSame('ACC-12345', $account->getAccountNumber());
        self::assertSame($household, $account->getHousehold());
    }

    public function testConstructorRejectsBlankAccountNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account number cannot be blank.');

        new Account(new Bank('Test Bank'), new Currency('PLN'), new AccountType('Test Type'), '   ', new Household('Test Household'));
    }

    public function testGetDisplayLabelCombinesBankTypeCurrencyAndAccountNumber(): void
    {
        $bank = new Bank('mBank');
        $currency = new Currency('PLN');
        $type = new AccountType('Osobiste');
        $household = new Household('Test Household');

        $account = new Account($bank, $currency, $type, 'ACC-12345', $household);

        self::assertSame('mBank / Osobiste / PLN / ACC-12345', $account->getDisplayLabel());
    }

    private function createAccount(): Account
    {
        return new Account(new Bank('Test Bank'), new Currency('PLN'), new AccountType('Test Type'), 'ACC-00000', new Household('Test Household'));
    }
}
