<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\MoneyExtension;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyExtensionTest extends TestCase
{
    private MoneyExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new MoneyExtension();
    }

    public function testFiltersExposeFormatAndAmountClass(): void
    {
        $filters = $this->extension->getFilters();

        self::assertCount(2, $filters);
        self::assertSame('money', $filters[0]->getName());
        self::assertSame('amountClass', $filters[1]->getName());
    }

    public function testFormatDelegatesToMoneyFormat(): void
    {
        $money = Money::fromMinorUnits(15000, 'PLN');

        self::assertSame('150,00', $this->extension->format($money));
    }

    public function testFormatWithCurrencySuffix(): void
    {
        $money = Money::fromMinorUnits(15000, 'PLN');

        self::assertSame('150,00 PLN', $this->extension->format($money, true));
    }

    public function testAmountClassReturnsTextSuccessForPositiveAmount(): void
    {
        self::assertSame('text-success', $this->extension->amountClass(1));
    }

    public function testAmountClassReturnsEmptyStringForZeroAmount(): void
    {
        self::assertSame('', $this->extension->amountClass(0));
    }

    public function testAmountClassReturnsEmptyStringForNegativeAmount(): void
    {
        self::assertSame('', $this->extension->amountClass(-1));
    }
}
