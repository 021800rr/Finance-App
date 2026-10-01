<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\FixedExpense;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FixedExpenseTest extends TestCase
{
    public function testSetDayRejectsValueBelowMinimum(): void
    {
        $fixedExpense = $this->createFixedExpense();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Day must be between 1 and 28.');

        $fixedExpense->setDay(0);
    }

    public function testSetDayRejectsValueAboveMaximum(): void
    {
        $fixedExpense = $this->createFixedExpense();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Day must be between 1 and 28.');

        $fixedExpense->setDay(29);
    }

    public function testSetAmountRejectsPositiveValue(): void
    {
        $fixedExpense = $this->createFixedExpense();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fixed expense amount must be between -99999999 and -1.');

        $fixedExpense->setAmount(100);
    }

    public function testSetAmountRejectsZero(): void
    {
        $fixedExpense = $this->createFixedExpense();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fixed expense amount must be between -99999999 and -1.');

        $fixedExpense->setAmount(0);
    }

    public function testSetAmountRejectsValueBelowLowerBound(): void
    {
        $fixedExpense = $this->createFixedExpense();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fixed expense amount must be between -99999999 and -1.');

        $fixedExpense->setAmount(-100000000);
    }

    public function testGetAmountAsMoneyUsesOwnCurrency(): void
    {
        $expense = $this->createFixedExpense();
        $expense->setAmount(-5000);
        $expense->setCurrency(new Currency('PLN'));

        $money = $expense->getAmountAsMoney();

        self::assertSame(-5000, $money->getMinorUnits());
        self::assertSame('PLN', $money->getCurrencyCode());
        self::assertSame(2, $money->getScale());
    }

    public function testConstructorSetsAllRequiredFields(): void
    {
        $contractor = new Contractor('Test Contractor');
        $currency = new Currency('PLN');

        $fixedExpense = new FixedExpense($contractor, 15, -5000, $currency);

        self::assertSame($contractor, $fixedExpense->getContractor());
        self::assertSame(15, $fixedExpense->getDay());
        self::assertSame(-5000, $fixedExpense->getAmount());
        self::assertSame($currency, $fixedExpense->getCurrency());
    }

    private function createFixedExpense(): FixedExpense
    {
        return new FixedExpense(new Contractor('Test Contractor'), 10, -1000, new Currency('PLN'));
    }
}
