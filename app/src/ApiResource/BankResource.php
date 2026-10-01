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
final class BankResource
{
    #[ApiProperty(writable: false)]
    public ?int $id = null;

    public ?string $name = null;

    public static function fromEntity(Bank $bank): self
    {
        $resource = new self();
        $resource->id = $bank->getId();
        $resource->name = $bank->getName();

        return $resource;
    }
}
