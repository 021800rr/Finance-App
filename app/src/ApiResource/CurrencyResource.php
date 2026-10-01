<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Entity\Currency;
use App\State\CurrencyProvider;

#[ApiResource(
    shortName: 'Currency',
    description: 'An ISO 4217 currency code known to the application.',
    operations: [
        new GetCollection(
            description: 'Lists all currencies, ordered by code.',
            provider: CurrencyProvider::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
        new Get(
            description: 'Retrieves a single currency by its code.',
            provider: CurrencyProvider::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class CurrencyResource
{
    #[ApiProperty(description: 'ISO 4217 currency code. Read-only, ignored on write.', writable: false, example: 'PLN')]
    public ?string $code = null;

    public static function fromEntity(Currency $currency): self
    {
        $resource = new self();
        $resource->code = $currency->getCode();

        return $resource;
    }
}
