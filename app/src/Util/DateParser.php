<?php

declare(strict_types=1);

namespace App\Util;

final class DateParser
{
    private function __construct()
    {
    }

    /**
     * For a date the validator (NotBlank + BusinessDate) has already accepted: anything else is a bug, not
     * a user error.
     */
    public static function parseValidated(?string $value): \DateTimeImmutable
    {
        $date = null !== $value ? self::tryParse($value) : null;
        if (null === $date) {
            throw new \LogicException('The date must be validated before it is parsed.');
        }

        return $date;
    }

    public static function tryParse(string $value): ?\DateTimeImmutable
    {
        if (1 !== preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) || str_starts_with($value, '0000-')) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (false === $date
            || (false !== $errors && (0 !== $errors['warning_count'] || 0 !== $errors['error_count']))
            || $date->format('Y-m-d') !== $value
        ) {
            return null;
        }

        return $date;
    }
}
