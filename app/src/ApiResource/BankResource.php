<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Entity\Bank;
use App\State\BankProvider;

#[ApiResource(
    shortName: 'Bank',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            provider: BankProvider::class,
        ),
        new Get(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            provider: BankProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final readonly class BankResource
{
    public function __construct(
        #[ApiProperty(description: 'Bank id.', required: true, example: 1)]
        public int $id,
        #[ApiProperty(description: 'Bank name.', required: true, example: 'Example bank')]
        public string $name,
    ) {
    }

    public static function fromEntity(Bank $bank): self
    {
        return new self($bank->getId(), $bank->getName());
    }
}
