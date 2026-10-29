<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Stores optional free text the way the web forms do: surrounding whitespace is removed and blank becomes null.
 */
final class OptionalText
{
    private function __construct()
    {
    }

    public static function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
