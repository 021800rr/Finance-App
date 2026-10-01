<?php

declare(strict_types=1);

namespace App\Tests\ValueObject;

use App\ValueObject\Quantity;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function testFromMinorUnitsStoresRawValue(): void
    {
        $quantity = Quantity::fromMinorUnits(15000);

        self::assertSame(15000, $quantity->getMinorUnits());
    }

    public function testFromDecimalStringParsesAtScaleFour(): void
    {
        $quantity = Quantity::fromDecimalString('1.5');

        self::assertSame(15000, $quantity->getMinorUnits());
    }

    public function testFromDecimalStringRoundsExtraDigits(): void
    {
        $quantity = Quantity::fromDecimalString('1.23456');

        // 1.23456 rounds to 1.2346 at scale 4.
        self::assertSame(12346, $quantity->getMinorUnits());
    }

    public function testFromDecimalStringThrowsOnNegativeInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Quantity::fromDecimalString('-1.5');
    }

    public function testFromDecimalStringThrowsOnInvalidInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Quantity::fromDecimalString('not-a-number');
    }

    public function testFromMinorUnitsThrowsOnNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Quantity::fromMinorUnits(-1);
    }

    public function testIsPositive(): void
    {
        self::assertTrue(Quantity::fromMinorUnits(1)->isPositive());
        self::assertFalse(Quantity::fromMinorUnits(0)->isPositive());
    }

    public function testToDecimalStringFormatsAtScaleFour(): void
    {
        self::assertSame('1.5000', Quantity::fromMinorUnits(15000)->toDecimalString());
        self::assertSame('0.0001', Quantity::fromMinorUnits(1)->toDecimalString());
        self::assertSame('0.0000', Quantity::fromMinorUnits(0)->toDecimalString());
    }
}
