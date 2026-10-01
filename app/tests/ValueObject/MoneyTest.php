<?php

declare(strict_types=1);

namespace App\Tests\ValueObject;

use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testFromMinorUnitsStoresRawValue(): void
    {
        $money = Money::fromMinorUnits(15000, 'pln');

        self::assertSame(15000, $money->getMinorUnits());
        self::assertSame('PLN', $money->getCurrencyCode());
        self::assertSame(2, $money->getScale());
    }

    public function testFromDecimalStringParsesAndRounds(): void
    {
        $money = Money::fromDecimalString('150.999', 'PLN');

        self::assertSame(15100, $money->getMinorUnits());
    }

    public function testZeroCreatesZeroValueMoney(): void
    {
        $money = Money::zero('CHF');

        self::assertTrue($money->isZero());
        self::assertSame('CHF', $money->getCurrencyCode());
    }

    public function testAddSameCurrencyAndScale(): void
    {
        $result = Money::fromMinorUnits(10000, 'PLN')->add(Money::fromMinorUnits(5000, 'PLN'));

        self::assertSame(15000, $result->getMinorUnits());
    }

    public function testAddThrowsOnDifferentCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromMinorUnits(10000, 'PLN')->add(Money::fromMinorUnits(5000, 'CHF'));
    }

    public function testAddThrowsOnDifferentScale(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromMinorUnits(10000, 'PLN', 2)->add(Money::fromMinorUnits(5000, 'PLN', 4));
    }

    public function testNegateFlipsSign(): void
    {
        $money = Money::fromMinorUnits(10000, 'PLN')->negate();

        self::assertSame(-10000, $money->getMinorUnits());
        self::assertTrue($money->isNegative());
    }

    public function testNegateTwiceReturnsToOriginal(): void
    {
        $money = Money::fromMinorUnits(10000, 'PLN');

        self::assertTrue($money->equals($money->negate()->negate()));
    }

    public function testConvertToPlnMultipliesByExchangeRate(): void
    {
        $money = Money::fromMinorUnits(10000, 'CHF'); // 100.00 CHF
        $result = $money->convertToPln('4.1234');

        self::assertSame(41234, $result->getMinorUnits()); // 412.34 PLN
        self::assertSame('PLN', $result->getCurrencyCode());
        self::assertSame(2, $result->getScale());
    }

    public function testConvertToPlnRoundsHalfUpInsteadOfTruncating(): void
    {
        $money = Money::fromMinorUnits(3, 'CHF'); // 0.03 CHF
        $result = $money->convertToPln('3.3333');

        // 0.03 * 3.3333 = 0.099999 -> rounds to 0.10, not truncated to 0.09.
        self::assertSame(10, $result->getMinorUnits());
    }

    public function testConvertToPlnThrowsWhenSourceScaleIsNotTwo(): void
    {
        $money = Money::fromMinorUnits(10000, 'CHF', 4);

        $this->expectException(\LogicException::class);

        $money->convertToPln('4.1234');
    }

    public function testConvertToPlnHandlesNegativeAmounts(): void
    {
        $money = Money::fromMinorUnits(-10000, 'CHF'); // -100.00 CHF (wydatek)
        $result = $money->convertToPln('4.1234');

        self::assertSame(-41234, $result->getMinorUnits());
    }

    public function testIsPositiveIsNegativeIsZero(): void
    {
        self::assertTrue(Money::fromMinorUnits(1, 'PLN')->isPositive());
        self::assertFalse(Money::fromMinorUnits(1, 'PLN')->isNegative());

        self::assertTrue(Money::fromMinorUnits(-1, 'PLN')->isNegative());
        self::assertFalse(Money::fromMinorUnits(-1, 'PLN')->isPositive());

        self::assertTrue(Money::fromMinorUnits(0, 'PLN')->isZero());
        self::assertFalse(Money::fromMinorUnits(0, 'PLN')->isPositive());
        self::assertFalse(Money::fromMinorUnits(0, 'PLN')->isNegative());
    }

    public function testEqualsComparesValueCurrencyAndScale(): void
    {
        self::assertTrue(Money::fromMinorUnits(100, 'PLN', 2)->equals(Money::fromMinorUnits(100, 'PLN', 2)));
        self::assertFalse(Money::fromMinorUnits(100, 'PLN', 2)->equals(Money::fromMinorUnits(100, 'CHF', 2)));
        self::assertFalse(Money::fromMinorUnits(100, 'PLN', 2)->equals(Money::fromMinorUnits(100, 'PLN', 4)));
        self::assertFalse(Money::fromMinorUnits(100, 'PLN', 2)->equals(Money::fromMinorUnits(101, 'PLN', 2)));
    }

    public function testCurrencyCodeIsUppercased(): void
    {
        self::assertSame('CHF', Money::fromMinorUnits(100, 'chf')->getCurrencyCode());
        self::assertSame('CHF', Money::fromDecimalString('1.00', 'chf')->getCurrencyCode());
    }

    public function testToDecimalStringFormatsAtGivenScale(): void
    {
        self::assertSame('150.00', Money::fromMinorUnits(15000, 'PLN')->toDecimalString());
        self::assertSame('4.1234', Money::fromMinorUnits(41234, 'PLN', 4)->toDecimalString());
        self::assertSame('-150.00', Money::fromMinorUnits(-15000, 'PLN')->toDecimalString());
    }

    public function testConvertToPlnRoundsDownWhenRemainderIsBelowHalf(): void
    {
        $money = Money::fromMinorUnits(1, 'CHF'); // 0.01 CHF
        $result = $money->convertToPln('0.4999');

        // 0.01 * 0.4999 = 0.004999 -> rounds down to 0.00, not up.
        self::assertSame(0, $result->getMinorUnits());
    }

    public function testFromDecimalStringHandlesNegativeAmounts(): void
    {
        $money = Money::fromDecimalString('-50.00', 'PLN');

        self::assertSame(-5000, $money->getMinorUnits());
        self::assertTrue($money->isNegative());
    }

    public function testFromDecimalStringThrowsOnInvalidInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromDecimalString('not-a-number', 'PLN');
    }

    public function testAddAcrossPositiveAndNegativeAmounts(): void
    {
        $result = Money::fromMinorUnits(-10000, 'PLN')->add(Money::fromMinorUnits(15000, 'PLN'));

        self::assertSame(5000, $result->getMinorUnits());
        self::assertTrue($result->isPositive());
    }

    public function testFormatWithoutCurrency(): void
    {
        $money = Money::fromMinorUnits(15000, 'PLN');

        self::assertSame('150,00', $money->format());
    }

    public function testFormatWithCurrency(): void
    {
        $money = Money::fromMinorUnits(15000, 'PLN');

        self::assertSame('150,00 PLN', $money->format(true));
    }

    public function testFormatRespectsCustomScale(): void
    {
        $money = Money::fromMinorUnits(41234, 'PLN', 4);

        self::assertSame('4,1234', $money->format());
    }

    public function testFormatHandlesNegativeAmounts(): void
    {
        $money = Money::fromMinorUnits(-15000, 'PLN');

        self::assertSame('-150,00', $money->format());
    }

    public function testConvertToPlnThrowsWhenAlreadyInSettlementCurrency(): void
    {
        $money = Money::fromMinorUnits(10000, 'PLN');

        $this->expectException(\LogicException::class);

        $money->convertToPln('4.1234');
    }
}
