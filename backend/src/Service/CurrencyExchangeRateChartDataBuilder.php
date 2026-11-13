<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\Currencies;
use App\Repository\CurrencyExchangeRateRepository;
use App\ValueObject\Money;

/**
 * @phpstan-import-type ChartRateRow from CurrencyExchangeRateRepository
 */
final class CurrencyExchangeRateChartDataBuilder
{
    /**
     * @param list<ChartRateRow> $rates
     *
     * @return array<string, array<string, float>> Bank name => [date => rate]
     */
    public function build(array $rates): array
    {
        $chartData = [];
        foreach ($rates as $rate) {
            $chartData[$rate['bank_name']][$rate['rate_date']] = (float) Money::fromMinorUnits(
                (int) $rate['rate_to_pln_minor_units'],
                Currencies::SETTLEMENT_CURRENCY_CODE,
                Currencies::EXCHANGE_RATE_SCALE,
            )->toDecimalString();
        }

        return $chartData;
    }
}
