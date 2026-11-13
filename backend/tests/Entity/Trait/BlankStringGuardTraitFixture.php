<?php

declare(strict_types=1);

namespace App\Tests\Entity\Trait;

use App\Entity\Trait\BlankStringGuardTrait;

final class BlankStringGuardTraitFixture
{
    use BlankStringGuardTrait;

    public static function notBlank(string $value, string $fieldName): void
    {
        self::assertNotBlank($value, $fieldName);
    }
}
