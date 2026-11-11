<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiResource\Input\GoldPriceInput;
use App\Constant\Currencies;
use App\Entity\GoldPrice;
use App\State\GoldPriceHistoryProvider;
use App\State\GoldPriceProcessor;
use App\State\GoldPriceProvider;
use App\ValueObject\Money;

/**
 * A gold price as the API returns it: every field is always there, and the OpenAPI document says so (a generated
 * client gets types without needless nulls). What a client sends is GoldPriceInput (input: on POST), where any field
 * may be missing until the validation says why.
 */
#[ApiResource(
    shortName: 'GoldPrice',
    description: 'A historical price point (in PLN) for a gold asset on a given date.',
    operations: [
        new GetCollection(
            uriTemplate: '/gold_prices/recent',
            openapi: new OpenApiOperation(
                summary: 'Retrieves the most recent gold prices.',
                description: 'Returns the most recent entries ordered by date then id descending, with no pagination.',
                security: [['JWT' => []]],
            ),
            paginationEnabled: false,
            provider: GoldPriceProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/gold_prices/chart',
            openapi: new OpenApiOperation(
                summary: 'Retrieves daily gold prices for the chart.',
                description: 'Historical daily gold prices, all assets and dates, no limit or pagination. '
                .'Returns the highest id per asset and date, ordered by date then id ascending, '
                .'matching the web chart.',
                security: [['JWT' => []]],
            ),
            paginationEnabled: false,
            provider: GoldPriceHistoryProvider::class,
        ),
        new Get(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Retrieves a single gold price entry by id.',
            provider: GoldPriceProvider::class,
        ),
        new Post(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Adds a new gold price entry. Multiple entries for the same gold asset and date are '
            .'allowed; the most recent date (and, within a date, highest id) is treated as the current price.',
            input: GoldPriceInput::class,
            processor: GoldPriceProcessor::class,
        ),
        new Delete(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Deletes a gold price entry.',
            provider: GoldPriceProvider::class,
            processor: GoldPriceProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final readonly class GoldPriceResource
{
    public function __construct(
        #[ApiProperty(description: 'Gold price entry id.', required: true, example: 1)]
        public int $id,
        #[ApiProperty(description: 'Id of the gold asset this price applies to.', required: true, example: 1)]
        public int $goldAssetId,
        #[ApiProperty(description: 'Price date, format YYYY-MM-DD.', required: true, example: '2026-09-29')]
        public string $priceDate,
        #[ApiProperty(
            description: 'Price in PLN as a decimal string, e.g. "250000.00".',
            required: true,
            example: '250000.00',
        )]
        public string $pricePln,
        #[ApiProperty(description: 'Currency code, always "PLN".', required: true, example: 'PLN')]
        public string $currencyCode,
    ) {
    }

    public static function fromEntity(GoldPrice $goldPrice): self
    {
        return new self(
            id: $goldPrice->getId(),
            goldAssetId: $goldPrice->getGoldAsset()->getId(),
            priceDate: $goldPrice->getPriceDate()->format('Y-m-d'),
            pricePln: $goldPrice->getPricePlnAsMoney()->toDecimalString(),
            currencyCode: $goldPrice->getCurrency()->getCode(),
        );
    }

    /**
     * Build a resource from a database row with price_date in YYYY-MM-DD format.
     *
     * @param array{
     *     id: int|string,
     *     gold_asset_id: int|string,
     *     currency_code: string,
     *     price_date: string,
     *     price_pln_minor_units: int|string,
     * } $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            goldAssetId: (int) $row['gold_asset_id'],
            priceDate: $row['price_date'],
            pricePln: Money::fromMinorUnits(
                (int) $row['price_pln_minor_units'],
                $row['currency_code'],
                Currencies::MINOR_UNIT_SCALE,
            )->toDecimalString(),
            currencyCode: $row['currency_code'],
        );
    }
}
