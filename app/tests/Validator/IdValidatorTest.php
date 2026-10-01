<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Constant\DatabaseLimits;
use App\Validator\IdValidator;
use PHPUnit\Framework\TestCase;

final class IdValidatorTest extends TestCase
{
    public function testTryParseAcceptsValidPositiveInteger(): void
    {
        self::assertSame(42, IdValidator::tryParse(42));
    }

    public function testTryParseAcceptsValidNumericString(): void
    {
        self::assertSame(42, IdValidator::tryParse('42'));
    }

    public function testTryParseRejectsZero(): void
    {
        self::assertNull(IdValidator::tryParse(0));
    }

    public function testTryParseRejectsNegativeNumber(): void
    {
        self::assertNull(IdValidator::tryParse(-1));
    }

    public function testTryParseRejectsNonNumericString(): void
    {
        self::assertNull(IdValidator::tryParse('abc'));
    }

    public function testTryParseRejectsValueAboveUpperBound(): void
    {
        self::assertNull(IdValidator::tryParse(DatabaseLimits::POSTGRES_INT4_MAX + 1));
    }

    public function testTryParseAcceptsUpperBoundValue(): void
    {
        self::assertSame(
            DatabaseLimits::POSTGRES_INT4_MAX,
            IdValidator::tryParse(DatabaseLimits::POSTGRES_INT4_MAX)
        );
    }

    public function testTryParseRejectsNull(): void
    {
        self::assertNull(IdValidator::tryParse(null));
    }
}
