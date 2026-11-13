<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Entity\GoldForm;
use App\State\GoldFormProvider;

#[ApiResource(
    shortName: 'GoldForm',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            provider: GoldFormProvider::class,
        ),
        new Get(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            provider: GoldFormProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final readonly class GoldFormResource
{
    public function __construct(
        #[ApiProperty(description: 'Gold form id.', required: true, example: 1)]
        public int $id,
        #[ApiProperty(description: 'Name of the form: a bar, a coin…', required: true, example: 'sztabka')]
        public string $name,
    ) {
    }

    public static function fromEntity(GoldForm $goldForm): self
    {
        return new self($goldForm->getId(), $goldForm->getName());
    }
}
