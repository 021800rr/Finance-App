<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Entity\Household;
use App\State\HouseholdProvider;

#[ApiResource(
    shortName: 'Household',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            provider: HouseholdProvider::class,
        ),
        new Get(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            provider: HouseholdProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final readonly class HouseholdResource
{
    public function __construct(
        #[ApiProperty(description: 'Household id.', required: true, example: 1)]
        public int $id,
        #[ApiProperty(description: 'Name of the household.', required: true, example: 'Nasze')]
        public string $name,
        #[ApiProperty(description: 'Whether its accounts count as own funds in the summary.', required: true)]
        public bool $isOwnFunds,
    ) {
    }

    public static function fromEntity(Household $household): self
    {
        return new self($household->getId(), $household->getName(), $household->isOwnFunds());
    }
}
