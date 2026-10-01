<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AccountType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AccountTypeTest extends TestCase
{
    public function testSetNameRejectsBlankValue(): void
    {
        $accountType = new AccountType('Valid Name');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account type name cannot be blank.');

        $accountType->setName('   ');
    }

    public function testConstructorSetsNameAndDefaultsIsRetirementToFalse(): void
    {
        $accountType = new AccountType('Test Type');

        self::assertSame('Test Type', $accountType->getName());
        self::assertFalse($accountType->isRetirement());
    }

    public function testConstructorAcceptsExplicitIsRetirement(): void
    {
        $accountType = new AccountType('Test Type', true);

        self::assertTrue($accountType->isRetirement());
    }

    public function testConstructorRejectsBlankName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account type name cannot be blank.');

        new AccountType('   ');
    }
}
