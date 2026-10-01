<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Contractor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ContractorTest extends TestCase
{
    public function testSetNameRejectsBlankValue(): void
    {
        $contractor = new Contractor('Valid Name');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Contractor name cannot be blank.');

        $contractor->setName('   ');
    }

    public function testConstructorSetsName(): void
    {
        $contractor = new Contractor('Test Contractor');

        self::assertSame('Test Contractor', $contractor->getName());
    }

    public function testConstructorRejectsBlankName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Contractor name cannot be blank.');

        new Contractor('   ');
    }
}
