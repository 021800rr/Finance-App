<?php

declare(strict_types=1);

namespace App\ValueObject\Trait;

trait DecimalParsingTrait
{
    /**
     * Parses an unsigned (always positive) decimal string into an integer in the smallest unit.
     * Rejects negative values — for use where negativity makes no sense (e.g. physical quantities).
     */
    private static function parseUnsignedDecimalToMinorUnits(string $decimal, int $scale): int
    {
        self::assertValidScale($scale);

        if (!preg_match('/^(\d+)(?:\.(\d+))?$/', $decimal, $matches)) {
            throw new \InvalidArgumentException("Invalid decimal string: $decimal");
        }

        return self::roundToMinorUnits((int) $matches[1], $matches[2] ?? '', $scale);
    }

    /**
     * Parses a decimal string (minus sign allowed) into an integer in the smallest unit.
     * For use where a negative value is meaningful in business terms (e.g. money amounts).
     */
    private static function parseSignedDecimalToMinorUnits(string $decimal, int $scale): int
    {
        self::assertValidScale($scale);

        if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $decimal, $matches)) {
            throw new \InvalidArgumentException("Invalid decimal string: $decimal");
        }

        $sign = '-' === $matches[1] ? -1 : 1;

        return $sign * self::roundToMinorUnits((int) $matches[2], $matches[3] ?? '', $scale);
    }

    /**
     * Combines the whole and fractional parts into a single integer at the given scale,
     * rounding (not truncating) any extra digits past the scale.
     */
    private static function roundToMinorUnits(int $wholePart, string $fractionStr, int $scale): int
    {
        if (strlen($fractionStr) <= $scale) {
            $fractionPart = (int) str_pad($fractionStr, $scale, '0');
        } else {
            $kept = substr($fractionStr, 0, $scale);
            $roundingDigit = (int) $fractionStr[$scale];
            $fractionPart = (int) $kept;
            if ($roundingDigit >= 5) {
                ++$fractionPart;
            }
        }

        $divisor = 10 ** $scale;
        if ($fractionPart >= $divisor) {
            ++$wholePart;
            $fractionPart -= $divisor;
        }

        return $wholePart * $divisor + $fractionPart;
    }

    /**
     * Converts an integer in the smallest unit back into a human-readable decimal string
     * (e.g. 12345 at scale=2 -> "123.45").
     */
    private static function minorUnitsToDecimalString(int $minorUnits, int $scale): string
    {
        self::assertValidScale($scale);

        $sign = $minorUnits < 0 ? '-' : '';
        $absoluteUnits = abs($minorUnits);

        if (0 === $scale) {
            return sprintf('%s%d', $sign, $absoluteUnits);
        }

        $divisor = 10 ** $scale;
        $wholePart = intdiv($absoluteUnits, $divisor);
        $fractionPart = $absoluteUnits % $divisor;

        return sprintf('%s%d.%0'.$scale.'d', $sign, $wholePart, $fractionPart);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private static function assertValidScale(int $scale): void
    {
        if ($scale < 0) {
            throw new \InvalidArgumentException("Scale must not be negative: $scale");
        }
    }
}
