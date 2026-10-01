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
final class HouseholdResource
{
    #[ApiProperty(writable: false)]
    public ?int $id = null;

    public ?string $name = null;

    public bool $isOwnFunds = true;

    public static function fromEntity(Household $household): self
    {
        $resource = new self();
        $resource->id = $household->getId();
        $resource->name = $household->getName();
        $resource->isOwnFunds = $household->isOwnFunds();

        return $resource;
    }
}
