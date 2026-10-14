<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Household;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HouseholdTest extends TestCase
{
    public function testConstructorSetsNameAndDefaultsIsOwnFundsToTrue(): void
    {
        $household = new Household('Test Household');

        self::assertSame('Test Household', $household->getName());
        self::assertTrue($household->isOwnFunds());
    }

    public function testConstructorAcceptsExplicitIsOwnFunds(): void
    {
        $household = new Household('Test Household', false);

        self::assertFalse($household->isOwnFunds());
    }

    public function testConstructorRejectsBlankName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Household name cannot be blank.');

        new Household('   ');
    }
}
