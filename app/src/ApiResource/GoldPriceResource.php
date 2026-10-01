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
use App\Entity\GoldPrice;
use App\State\GoldPriceProcessor;
use App\State\GoldPriceProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'GoldPrice',
    description: 'A historical price point (in PLN) for a gold asset on a given date.',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Lists all gold price entries.',
            provider: GoldPriceProvider::class,
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

    #[ApiProperty(description: 'Price date, format YYYY-MM-DD. Required.', example: '2026-09-29')]
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
}
