<?php

declare(strict_types=1);

namespace App\Tests\ValueObject\Trait;

use App\ValueObject\Trait\DecimalParsingTrait;

final class DecimalParsingTraitTestHarness
{
    use DecimalParsingTrait;

    public static function unsignedToMinorUnits(string $decimal, int $scale): int
    {
        return self::parseUnsignedDecimalToMinorUnits($decimal, $scale);
    }

    public static function signedToMinorUnits(string $decimal, int $scale): int
    {
        return self::parseSignedDecimalToMinorUnits($decimal, $scale);
    }

    public static function toDecimalString(int $minorUnits, int $scale): string
    {
        return self::minorUnitsToDecimalString($minorUnits, $scale);
    }
}
