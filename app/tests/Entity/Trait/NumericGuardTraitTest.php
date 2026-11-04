<?php

declare(strict_types=1);

namespace App\Tests\Entity\Trait;

use PHPUnit\Framework\TestCase;

final class NumericGuardTraitTest extends TestCase
{
    public function testAssertNotZeroAcceptsPositiveValue(): void
    {
        NumericGuardTraitFixture::notZero(1, 'Test');
        $this->addToAssertionCount(1);
    }

    public function testAssertNotZeroAcceptsNegativeValue(): void
    {
        NumericGuardTraitFixture::notZero(-1, 'Test');
        $this->addToAssertionCount(1);
    }

    public function testAssertNotZeroRejectsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Test cannot be zero.');

        NumericGuardTraitFixture::notZero(0, 'Test');
    }

    public function testAssertInRangeAcceptsValueWithinBounds(): void
    {
        NumericGuardTraitFixture::inRange(15, 1, 28, 'Test');
        $this->addToAssertionCount(1);
    }

    public function testAssertInRangeAcceptsLowerBound(): void
    {
        NumericGuardTraitFixture::inRange(1, 1, 28, 'Test');
        $this->addToAssertionCount(1);
    }

    public function testAssertInRangeAcceptsUpperBound(): void
    {
        NumericGuardTraitFixture::inRange(28, 1, 28, 'Test');
        $this->addToAssertionCount(1);
    }

    public function testAssertInRangeRejectsValueBelowMinimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Test must be between 1 and 28.');

        NumericGuardTraitFixture::inRange(0, 1, 28, 'Test');
    }

    public function testAssertInRangeRejectsValueAboveMaximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NumericGuardTraitFixture::inRange(29, 1, 28, 'Test');
    }
}
