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
use App\Constant\Currencies;
use App\Entity\GoldPrice;
use App\State\GoldPriceHistoryProvider;
use App\State\GoldPriceProcessor;
use App\State\GoldPriceProvider;
use App\Util\BusinessDateRange;
use App\ValueObject\Money;
use Symfony\Component\Validator\Constraints as Assert;

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
final class GoldPriceResource
{
    #[ApiProperty(description: 'Gold price entry id. Read-only, ignored on write.', writable: false, example: 1)]
    public ?int $id = null;

    #[ApiProperty(description: 'Id of an existing gold asset this price applies to. Required.', example: 1)]
    #[Assert\NotNull(message: 'gold_price.asset_not_null')]
    public ?int $goldAssetId = null;

    #[ApiProperty(description: 'Price date, format YYYY-MM-DD, from '.BusinessDateRange::MIN_DATE.' to '
        .BusinessDateRange::MAX_DATE.' inclusive. Required.', example: '2026-09-29')]
    #[Assert\NotBlank(message: 'common.date_not_blank')]
    public ?string $priceDate = null;

    #[ApiProperty(
        description: 'Price in PLN as a decimal string, e.g. "250000.00". Required.',
        example: '250000.00',
    )]
    #[Assert\NotBlank(message: 'gold_price.price_not_blank')]
    public ?string $pricePln = null;

    #[ApiProperty(description: 'Currency code. Always PLN. Read-only, ignored on write.', writable: false, example: 'PLN')]
    public ?string $currencyCode = null;

    public static function fromEntity(GoldPrice $goldPrice): self
    {
        $resource = new self();
        $resource->id = $goldPrice->getId();
        $resource->goldAssetId = $goldPrice->getGoldAsset()->getId();
        $resource->priceDate = $goldPrice->getPriceDate()->format('Y-m-d');
        $resource->pricePln = $goldPrice->getPricePlnAsMoney()->toDecimalString();
        $resource->currencyCode = $goldPrice->getCurrency()->getCode();

        return $resource;
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
        $resource = new self();
        $resource->id = (int) $row['id'];
        $resource->goldAssetId = (int) $row['gold_asset_id'];
        $resource->priceDate = $row['price_date'];
        $resource->pricePln = Money::fromMinorUnits(
            (int) $row['price_pln_minor_units'],
            $row['currency_code'],
            Currencies::MINOR_UNIT_SCALE,
        )->toDecimalString();
        $resource->currencyCode = $row['currency_code'];

        return $resource;
    }
}
