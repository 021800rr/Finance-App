<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\Currencies;
use App\Repository\GoldPriceRepository;
use App\ValueObject\Money;

/**
 * @phpstan-import-type ChartPriceRow from GoldPriceRepository
 */
final class GoldPriceChartDataBuilder
{
    /**
     * @param list<ChartPriceRow> $prices
     *
     * @return list<array{assetId: int, label: string, prices: array<string, float>}>
     */
    public function build(array $prices): array
    {
        $data = [];
        foreach ($prices as $price) {
            $assetId = (int) $price['gold_asset_id'];
            $data[$assetId] ??= ['assetId' => $assetId, 'label' => $price['asset_description'], 'prices' => []];
            $data[$assetId]['prices'][$price['price_date']] = (float) Money::fromMinorUnits(
                (int) $price['price_pln_minor_units'],
                Currencies::SETTLEMENT_CURRENCY_CODE,
                Currencies::MINOR_UNIT_SCALE,
            )->toDecimalString();
        }

        return array_values($data);
    }
}
