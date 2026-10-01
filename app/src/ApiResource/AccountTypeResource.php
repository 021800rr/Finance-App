<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Entity\AccountType;
use App\State\AccountTypeProvider;

#[ApiResource(
    shortName: 'AccountType',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            provider: AccountTypeProvider::class,
        ),
        new Get(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            provider: AccountTypeProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class AccountTypeResource
{
    #[ApiProperty(writable: false)]
    public ?int $id = null;

    public ?string $name = null;

    public bool $isRetirement = false;

    public static function fromEntity(AccountType $accountType): self
    {
        $resource = new self();
        $resource->id = $accountType->getId();
        $resource->name = $accountType->getName();
        $resource->isRetirement = $accountType->isRetirement();

        return $resource;
    }
}
