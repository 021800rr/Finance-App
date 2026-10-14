<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Bank;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BankTest extends TestCase
{
    public function testConstructorSetsName(): void
    {
        $bank = new Bank('Test Bank');

        self::assertSame('Test Bank', $bank->getName());
    }

    public function testConstructorRejectsBlankName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bank name cannot be blank.');

        new Bank('   ');
    }
}
