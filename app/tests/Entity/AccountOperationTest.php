<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AccountOperationTest extends TestCase
{
    public function testSetAmountRejectsZero(): void
    {
        $operation = $this->createOperation();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be zero.');

        $operation->setAmount(0);
    }

    public function testSetAmountRejectsValueAboveUpperBound(): void
    {
        $operation = $this->createOperation();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be between -99999999 and 99999999.');

        $operation->setAmount(100000000);
    }

    public function testSetAmountRejectsValueBelowLowerBound(): void
    {
        $operation = $this->createOperation();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be between -99999999 and 99999999.');

        $operation->setAmount(-100000000);
    }

    public function testGetAmountAsMoneyUsesOwnCurrency(): void
    {
        $operation = new AccountOperation(
            $this->createAccount('CHF'),
            new Contractor('Test Contractor'),
            new \DateTimeImmutable(),
            -1000,
            new Currency('CHF')
        );
        $operation->setAmount(-15000);

        $money = $operation->getAmountAsMoney();

        self::assertSame(-15000, $money->getMinorUnits());
        self::assertSame('CHF', $money->getCurrencyCode());
        self::assertSame(2, $money->getScale());
    }

    public function testConstructorSetsAllRequiredFields(): void
    {
        $account = $this->createAccount();
        $contractor = new Contractor('Test Contractor');
        $currency = new Currency('PLN');
        $date = new \DateTimeImmutable();

        $operation = new AccountOperation($account, $contractor, $date, -15000, $currency);

        self::assertSame($account, $operation->getAccount());
        self::assertSame($contractor, $operation->getContractor());
        self::assertSame($date, $operation->getOperationDate());
        self::assertSame(-15000, $operation->getAmount());
        self::assertSame($currency, $operation->getCurrency());
    }

    public function testConstructorRejectsCurrencyOtherThanTheAccountCurrency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operation currency (CHF) must match the account currency (PLN).');

        new AccountOperation(
            $this->createAccount('PLN'),
            new Contractor('Test Contractor'),
            new \DateTimeImmutable(),
            -1000,
            new Currency('CHF')
        );
    }

    private function createOperation(): AccountOperation
    {
        return new AccountOperation(
            $this->createAccount(),
            new Contractor('Test Contractor'),
            new \DateTimeImmutable(),
            -1000,
            new Currency('PLN')
        );
    }

    private function createAccount(string $currencyCode = 'PLN'): Account
    {
        return new Account(
            new Bank('Test Bank'),
            new Currency($currencyCode),
            new AccountType('Test Type'),
            'ACC-TEST-' . uniqid(),
            new Household('Test Household')
        );
    }
}
