<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Currency;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    public function testConstructorRejectsBlankCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency code cannot be blank.');

        new Currency('   ');
    }

    public function testConstructorRejectsNonExistentCurrencyCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency code must be a valid ISO 4217 code.');

        new Currency('ZZZ');
    }
}
