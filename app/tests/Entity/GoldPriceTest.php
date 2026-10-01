<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Currency;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GoldPriceTest extends TestCase
{
    public function testConstructorRejectsZeroPrice(): void
    {
        $asset = $this->createGoldAsset();
        $currency = new Currency('PLN');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price must be between 1 and 99999999.');

        new GoldPrice($asset, new DateTimeImmutable(), 0, $currency);
    }

    public function testConstructorRejectsPriceAboveUpperBound(): void
    {
        $asset = $this->createGoldAsset();
        $currency = new Currency('PLN');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price must be between 1 and 99999999.');

        new GoldPrice($asset, new DateTimeImmutable(), 100000000, $currency);
    }

    public function testSetPricePlnRejectsZero(): void
    {
        $asset = $this->createGoldAsset();
        $currency = new Currency('PLN');
        $price = new GoldPrice($asset, new DateTimeImmutable(), 10000, $currency);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price must be between 1 and 99999999.');

        $price->setPricePln(0);
    }

    public function testGetPricePlnAsMoneyUsesOwnCurrency(): void
    {
        $goldPrice = new GoldPrice($this->createGoldAsset(), new DateTimeImmutable(), 500000, new Currency('PLN'));

        $money = $goldPrice->getPricePlnAsMoney();

        self::assertSame(500000, $money->getMinorUnits());
        self::assertSame('PLN', $money->getCurrencyCode());
        self::assertSame(2, $money->getScale());
    }

    private function createGoldAsset(): GoldAsset
    {
        $goldForm = new GoldForm('sztabka');

        return new GoldAsset($goldForm, 10000, 'Test');
    }
}
