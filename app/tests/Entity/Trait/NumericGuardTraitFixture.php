<?php

declare(strict_types=1);

namespace App\Tests\Entity\Trait;

use App\Entity\Trait\NumericGuardTrait;

final class NumericGuardTraitFixture
{
    use NumericGuardTrait;

    public static function positive(int $value, string $fieldName): void
    {
        self::assertPositive($value, $fieldName);
    }

    public static function negative(int $value, string $fieldName): void
    {
        self::assertNegative($value, $fieldName);
    }

    public static function notZero(int $value, string $fieldName): void
    {
        self::assertNotZero($value, $fieldName);
    }

    public static function inRange(int $value, int $min, int $max, string $fieldName): void
    {
        self::assertInRange($value, $min, $max, $fieldName);
    }
}
