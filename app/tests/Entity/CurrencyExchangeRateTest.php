<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CurrencyExchangeRateTest extends TestCase
{
    public function testSetRateToPlnRejectsZero(): void
    {
        $rate = $this->createRate();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate must be between 1 and 999999.');

        $rate->setRateToPln(0);
    }

    public function testSetRateToPlnRejectsValueAboveUpperBound(): void
    {
        $rate = $this->createRate();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate must be between 1 and 999999.');

        $rate->setRateToPln(1000000);
    }

    public function testGetRateToPlnAsMoneyUsesScaleFour(): void
    {
        $rate = new CurrencyExchangeRate(new Bank('Test Bank'), new Currency('CHF'), new \DateTimeImmutable(), 41234);

        $money = $rate->getRateToPlnAsMoney();

        self::assertSame(41234, $money->getMinorUnits());
        self::assertSame(4, $money->getScale());
        self::assertSame('4.1234', $money->toDecimalString());
    }

    public function testConstructorSetsAllRequiredFields(): void
    {
        $bank = new Bank('Test Bank');
        $currency = new Currency('CHF');
        $date = new \DateTimeImmutable();

        $rate = new CurrencyExchangeRate($bank, $currency, $date, 41234);

        self::assertSame($bank, $rate->getBank());
        self::assertSame($currency, $rate->getCurrency());
        self::assertSame($date, $rate->getRateDate());
        self::assertSame(41234, $rate->getRateToPln());
    }

    private function createRate(): CurrencyExchangeRate
    {
        return new CurrencyExchangeRate(new Bank('Test Bank'), new Currency('CHF'), new \DateTimeImmutable(), 1000);
    }
}
