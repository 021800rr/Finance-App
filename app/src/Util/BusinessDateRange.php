<?php

declare(strict_types=1);

namespace App\Util;

final class BusinessDateRange
{
    public const int MIN_YEAR = 2025;
    public const int MAX_YEAR = 2070;
    public const string MIN_DATE = self::MIN_YEAR.'-01-01';
    public const string MAX_DATE = self::MAX_YEAR.'-12-31';
    public const string ERROR_MESSAGE = 'Date must be between '.self::MIN_DATE.' and '.self::MAX_DATE.' (inclusive).';

    private function __construct()
    {
    }

    public static function contains(\DateTimeInterface $date): bool
    {
        // The rule concerns the calendar date, regardless of its time or time zone.
        $year = (int) $date->format('Y');

        return $year >= self::MIN_YEAR && $year <= self::MAX_YEAR;
    }

    public static function assertContains(\DateTimeInterface $date): void
    {
        if (!self::contains($date)) {
            throw new \InvalidArgumentException(self::ERROR_MESSAGE);
        }
    }
}
