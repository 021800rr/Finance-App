<?php

declare(strict_types=1);

namespace App\Constant;

final class Currencies
{
    public const string SETTLEMENT_CURRENCY_CODE = 'PLN';
    public const int MINOR_UNIT_SCALE = 2;
    public const int EXCHANGE_RATE_SCALE = 4;
    public const string TRACKED_FOREIGN_CURRENCY_CODE = 'CHF';
}
