<?php

declare(strict_types=1);

namespace App\Tests\ValueObject\Trait;

use PHPUnit\Framework\TestCase;

final class DecimalParsingTraitTest extends TestCase
{
    public function testParseUnsignedPadsShortFraction(): void
    {
        self::assertSame(15000, DecimalParsingTraitTestHarness::unsignedToMinorUnits('150', 2));
        self::assertSame(15050, DecimalParsingTraitTestHarness::unsignedToMinorUnits('150.5', 2));
        self::assertSame(15050, DecimalParsingTraitTestHarness::unsignedToMinorUnits('150.50', 2));
    }

    public function testParseUnsignedRoundsHalfUpOnExtraDigits(): void
    {
        self::assertSame(600, DecimalParsingTraitTestHarness::unsignedToMinorUnits('5.999', 2));
        self::assertSame(1050, DecimalParsingTraitTestHarness::unsignedToMinorUnits('10.4999', 2));
        self::assertSame(1013, DecimalParsingTraitTestHarness::unsignedToMinorUnits('10.1259', 2));
    }

    public function testParseUnsignedZero(): void
    {
        self::assertSame(0, DecimalParsingTraitTestHarness::unsignedToMinorUnits('0', 2));
        self::assertSame(0, DecimalParsingTraitTestHarness::unsignedToMinorUnits('0.00', 2));
    }

    public function testParseUnsignedThrowsOnNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DecimalParsingTraitTestHarness::unsignedToMinorUnits('-1.00', 2);
    }

    public function testParseUnsignedThrowsOnMalformedInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DecimalParsingTraitTestHarness::unsignedToMinorUnits('12.34.56', 2);
    }

    public function testParseUnsignedThrowsOnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DecimalParsingTraitTestHarness::unsignedToMinorUnits('', 2);
    }

    public function testParseUnsignedThrowsOnNonNumericInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DecimalParsingTraitTestHarness::unsignedToMinorUnits('abc', 2);
    }

    public function testParseUnsignedThrowsOnNegativeScale(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DecimalParsingTraitTestHarness::unsignedToMinorUnits('100.00', -1);
    }

    public function testParseSignedHandlesPositiveAndNegative(): void
    {
        self::assertSame(15000, DecimalParsingTraitTestHarness::signedToMinorUnits('150.00', 2));
        self::assertSame(-15000, DecimalParsingTraitTestHarness::signedToMinorUnits('-150.00', 2));
    }

    public function testParseSignedRoundsMagnitudeBeforeApplyingSign(): void
    {
        // -5.999 rounds to -6.00, not -5.99 (rounding half up on the magnitude, then negate).
        self::assertSame(-600, DecimalParsingTraitTestHarness::signedToMinorUnits('-5.999', 2));
    }

    public function testParseSignedNegativeZeroIsPlainZero(): void
    {
        self::assertSame(0, DecimalParsingTraitTestHarness::signedToMinorUnits('-0.00', 2));
    }

    public function testParseSignedThrowsOnMalformedInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DecimalParsingTraitTestHarness::signedToMinorUnits('--1.00', 2);
    }

    public function testParseSignedThrowsOnPlusSign(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DecimalParsingTraitTestHarness::signedToMinorUnits('+1.00', 2);
    }

    public function testParseSignedThrowsOnNegativeScale(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DecimalParsingTraitTestHarness::signedToMinorUnits('-100.00', -1);
    }

    public function testMinorUnitsToDecimalStringScaleTwo(): void
    {
        self::assertSame('150.00', DecimalParsingTraitTestHarness::toDecimalString(15000, 2));
        self::assertSame('0.05', DecimalParsingTraitTestHarness::toDecimalString(5, 2));
    }

    public function testMinorUnitsToDecimalStringScaleFour(): void
    {
        self::assertSame('4.1234', DecimalParsingTraitTestHarness::toDecimalString(41234, 4));
    }

    public function testMinorUnitsToDecimalStringNegative(): void
    {
        self::assertSame('-150.00', DecimalParsingTraitTestHarness::toDecimalString(-15000, 2));
    }

    public function testMinorUnitsToDecimalStringZero(): void
    {
        self::assertSame('0.00', DecimalParsingTraitTestHarness::toDecimalString(0, 2));
    }

    public function testMinorUnitsToDecimalStringAtScaleZero(): void
    {
        self::assertSame('500', DecimalParsingTraitTestHarness::toDecimalString(500, 0));
    }

    public function testMinorUnitsToDecimalStringThrowsOnNegativeScale(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DecimalParsingTraitTestHarness::toDecimalString(100, -1);
    }

    public function testRoundTripPreservesValueAtScaleTwo(): void
    {
        $minorUnits = DecimalParsingTraitTestHarness::signedToMinorUnits('1234.56', 2);
        self::assertSame('1234.56', DecimalParsingTraitTestHarness::toDecimalString($minorUnits, 2));
    }
}
