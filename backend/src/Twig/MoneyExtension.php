<?php

declare(strict_types=1);

namespace App\Twig;

use App\ValueObject\Money;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class MoneyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->format(...)),
            new TwigFilter('amountClass', $this->amountClass(...)),
        ];
    }

    public function format(Money $money, bool $withCurrency = false): string
    {
        return $money->format($withCurrency);
    }

    public function amountClass(int $amount): string
    {
        return $amount > 0 ? 'text-success' : '';
    }
}
