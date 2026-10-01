<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\GoldForm;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GoldFormTest extends TestCase
{
    public function testSetNameRejectsBlankValue(): void
    {
        $goldForm = new GoldForm('Valid Name');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gold form name cannot be blank.');

        $goldForm->setName('   ');
    }

    public function testConstructorSetsName(): void
    {
        $goldForm = new GoldForm('Test Gold Form');

        self::assertSame('Test Gold Form', $goldForm->getName());
    }

    public function testConstructorRejectsBlankName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gold form name cannot be blank.');

        new GoldForm('   ');
    }
}
