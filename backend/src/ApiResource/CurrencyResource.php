<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Entity\Currency;
use App\State\CurrencyProvider;

#[ApiResource(
    shortName: 'Currency',
    description: 'An ISO 4217 currency code known to the application.',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Lists all currencies, ordered by code.',
            provider: CurrencyProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final readonly class CurrencyResource
{
    public function __construct(
        #[ApiProperty(description: 'ISO 4217 currency code.', required: true, example: 'PLN')]
        public string $code,
    ) {
    }

    public static function fromEntity(Currency $currency): self
    {
        return new self($currency->getCode());
    }
}
