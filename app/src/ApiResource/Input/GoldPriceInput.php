<?php

declare(strict_types=1);

namespace App\ApiResource\Input;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\GoldAsset;
use App\Util\BusinessDateRange;
use App\Validator\BusinessDate;
use App\Validator\DecimalString;
use App\Validator\RecordExists;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a client sends to add a gold price (POST). Any field may be missing or null here: the validation then says
 * which one and why, with a code the clients translate. What the API answers is GoldPriceResource, where every field
 * is always there.
 */
final class GoldPriceInput
{
    #[ApiProperty(description: 'Id of an existing gold asset this price applies to. Required.', example: 1)]
    #[Assert\NotNull(message: 'gold_price.asset_not_null')]
    #[RecordExists(GoldAsset::class, message: 'gold_asset.not_found')]
    public ?int $goldAssetId = null;

    #[ApiProperty(description: 'Price date, format YYYY-MM-DD, from '.BusinessDateRange::MIN_DATE.' to '
        .BusinessDateRange::MAX_DATE.' inclusive. Required.', example: '2026-09-29')]
    #[Assert\NotBlank(message: 'common.date_not_blank')]
    #[BusinessDate]
    public ?string $priceDate = null;

    #[ApiProperty(
        description: 'Price in PLN as a decimal string, e.g. "250000.00". Required.',
        example: '250000.00',
    )]
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'gold_price.price_not_blank', normalizer: 'trim'),
        new DecimalString(),
        new Assert\Range(
            min: 0.01,
            max: 999999.99,
            notInRangeMessage: 'gold_price.price_out_of_range',
            invalidMessage: 'common.numeric_invalid',
        ),
    ])]
    public ?string $pricePln = null;
}
