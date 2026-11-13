<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\DateParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateParserTest extends TestCase
{
    #[DataProvider('validDates')]
    public function testAcceptsCalendarDatesAtMidnight(string $value): void
    {
        $date = DateParser::tryParse($value);

        self::assertNotNull($date);
        self::assertSame($value, $date->format('Y-m-d'));
        self::assertSame('00:00:00.000000', $date->format('H:i:s.u'));
    }

    #[DataProvider('invalidDates')]
    public function testRejectsInvalidCalendarDatesAndOtherFormats(string $value): void
    {
        self::assertNull(DateParser::tryParse($value));
    }

    public function testAnEarlierParseWarningDoesNotRejectTheNextValidDate(): void
    {
        self::assertNull(DateParser::tryParse('2026-02-31'));
        self::assertNotNull(DateParser::tryParse('2026-02-28'));
    }

    public function testParseValidatedReturnsTheDateAtMidnight(): void
    {
        self::assertSame('2026-01-15 00:00:00', DateParser::parseValidated('2026-01-15')->format('Y-m-d H:i:s'));
    }

    #[DataProvider('unvalidatedDates')]
    public function testParseValidatedTreatsADateTheValidatorMissedAsABug(?string $value): void
    {
        $this->expectException(\LogicException::class);

        DateParser::parseValidated($value);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function unvalidatedDates(): iterable
    {
        yield 'missing' => [null];
        yield 'not a date' => ['2026-02-31'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validDates(): iterable
    {
        yield 'ordinary date' => ['2026-01-15'];
        yield 'last day of month' => ['2026-04-30'];
        yield 'leap day' => ['2024-02-29'];
        yield 'leap century' => ['2000-02-29'];
        yield 'first year' => ['0001-01-01'];
        yield 'last four-digit year' => ['9999-12-31'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDates(): iterable
    {
        yield '31 February' => ['2026-02-31'];
        yield '29 February in a non-leap year' => ['2025-02-29'];
        yield 'non-leap century' => ['1900-02-29'];
        yield '31 April' => ['2026-04-31'];
        yield 'zero day' => ['2026-01-00'];
        yield 'zero month' => ['2026-00-15'];
        yield 'month overflow' => ['2026-13-15'];
        yield 'year zero' => ['0000-01-01'];
        yield 'five-digit year' => ['10000-01-01'];
        yield 'month without padding' => ['2026-1-15'];
        yield 'day without padding' => ['2026-01-5'];
        yield 'slashes' => ['2026/01/15'];
        yield 'relative date' => ['tomorrow'];
        yield 'current instant' => ['now'];
        yield 'empty string' => [''];
        yield 'whitespace only' => [' '];
        yield 'leading whitespace' => [' 2026-01-15'];
        yield 'trailing whitespace' => ['2026-01-15 '];
        yield 'trailing newline' => ["2026-01-15\n"];
        yield 'time appended' => ['2026-01-15 12:00:00'];
        yield 'ISO timestamp' => ['2026-01-15T12:00:00+01:00'];
        yield 'NUL byte' => ["2026-01-15\0"];
    }
}
