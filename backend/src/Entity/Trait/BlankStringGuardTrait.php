<?php

declare(strict_types=1);

namespace App\Entity\Trait;

trait BlankStringGuardTrait
{
    private static function assertNotBlank(string $value, string $fieldName): void
    {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException("{$fieldName} cannot be blank.");
        }
    }
}
