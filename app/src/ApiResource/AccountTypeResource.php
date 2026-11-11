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
final readonly class AccountTypeResource
{
    public function __construct(
        #[ApiProperty(description: 'Account type id.', required: true, example: 1)]
        public int $id,
        #[ApiProperty(description: 'Name of the type.', required: true, example: 'ROR')]
        public string $name,
        #[ApiProperty(description: 'Whether the accounts of this type count as retirement savings.', required: true)]
        public bool $isRetirement,
    ) {
    }

    public static function fromEntity(AccountType $accountType): self
    {
        return new self($accountType->getId(), $accountType->getName(), $accountType->isRetirement());
    }
}
