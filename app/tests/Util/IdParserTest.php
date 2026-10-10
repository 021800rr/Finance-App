<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Constant\DatabaseLimits;
use App\Util\IdParser;
use PHPUnit\Framework\TestCase;

final class IdParserTest extends TestCase
{
    public function testTryParseAcceptsValidPositiveInteger(): void
    {
        self::assertSame(42, IdParser::tryParse(42));
    }

    public function testTryParseAcceptsValidNumericString(): void
    {
        self::assertSame(42, IdParser::tryParse('42'));
    }

    public function testTryParseRejectsZero(): void
    {
        self::assertNull(IdParser::tryParse(0));
    }

    public function testTryParseRejectsNegativeNumber(): void
    {
        self::assertNull(IdParser::tryParse(-1));
    }

    public function testTryParseRejectsNonNumericString(): void
    {
        self::assertNull(IdParser::tryParse('abc'));
    }

    public function testTryParseRejectsValueAboveUpperBound(): void
    {
        self::assertNull(IdParser::tryParse(DatabaseLimits::POSTGRES_INT4_MAX + 1));
    }

    public function testTryParseAcceptsUpperBoundValue(): void
    {
        self::assertSame(
            DatabaseLimits::POSTGRES_INT4_MAX,
            IdParser::tryParse(DatabaseLimits::POSTGRES_INT4_MAX)
        );
    }

    public function testTryParseRejectsNull(): void
    {
        self::assertNull(IdParser::tryParse(null));
    }
}
