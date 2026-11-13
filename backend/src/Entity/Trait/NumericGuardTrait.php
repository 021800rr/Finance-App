<?php

declare(strict_types=1);

namespace App\Entity\Trait;

trait NumericGuardTrait
{
    private static function assertNotZero(int $value, string $fieldName): void
    {
        if (0 === $value) {
            throw new \InvalidArgumentException("{$fieldName} cannot be zero.");
        }
    }

    private static function assertInRange(int $value, int $min, int $max, string $fieldName): void
    {
        if ($value < $min || $value > $max) {
            throw new \InvalidArgumentException("{$fieldName} must be between {$min} and {$max}.");
        }
    }
}
