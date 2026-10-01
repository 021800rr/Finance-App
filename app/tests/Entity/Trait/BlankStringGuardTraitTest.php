<?php

declare(strict_types=1);

namespace App\Tests\Entity\Trait;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BlankStringGuardTraitTest extends TestCase
{
    public function testAssertNotBlankAcceptsNonEmptyString(): void
    {
        BlankStringGuardTraitFixture::notBlank('Test value', 'Test');
        $this->addToAssertionCount(1);
    }

    public function testAssertNotBlankRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Test cannot be blank.');

        BlankStringGuardTraitFixture::notBlank('', 'Test');
    }

    public function testAssertNotBlankRejectsWhitespaceOnlyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Test cannot be blank.');

        BlankStringGuardTraitFixture::notBlank('   ', 'Test');
    }

    public function testAssertNotBlankRejectsTabsAndNewlines(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BlankStringGuardTraitFixture::notBlank("\t\n  \t", 'Test');
    }

    public function testAssertNotBlankAcceptsStringWithLeadingAndTrailingWhitespace(): void
    {
        BlankStringGuardTraitFixture::notBlank('  Test value  ', 'Test');
        $this->addToAssertionCount(1);
    }
}
