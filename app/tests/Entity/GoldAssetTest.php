<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GoldAssetTest extends TestCase
{
    public function testSetDescriptionRejectsBlankValue(): void
    {
        $asset = $this->createGoldAsset();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Description cannot be blank.');

        $asset->setDescription('   ');
    }

    public function testSetOuncesRejectsZero(): void
    {
        $asset = $this->createGoldAsset();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ounces must be between 1 and 9999999.');

        $asset->setOunces(0);
    }

    public function testSetOuncesRejectsValueAboveUpperBound(): void
    {
        $asset = $this->createGoldAsset();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ounces must be between 1 and 9999999.');

        $asset->setOunces(10000000);
    }

    public function testConstructorSetsAllRequiredFields(): void
    {
        $form = new GoldForm('sztabka');

        $asset = new GoldAsset($form, 10000, 'Test description');

        self::assertSame($form, $asset->getForm());
        self::assertSame(10000, $asset->getOunces());
        self::assertSame('Test description', $asset->getDescription());
    }

    public function testConstructorRejectsBlankDescription(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Description cannot be blank.');

        new GoldAsset(new GoldForm('sztabka'), 10000, '   ');
    }

    public function testConstructorRejectsZeroOunces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ounces must be between 1 and 9999999.');

        new GoldAsset(new GoldForm('sztabka'), 0, 'Test description');
    }

    private function createGoldAsset(): GoldAsset
    {
        return new GoldAsset(new GoldForm('sztabka'), 10000, 'Test description');
    }
}
