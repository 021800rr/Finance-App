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
final class GoldFormResource
{
    #[ApiProperty(writable: false)]
    public ?int $id = null;

    public ?string $name = null;

    public static function fromEntity(GoldForm $goldForm): self
    {
        $resource = new self();
        $resource->id = $goldForm->getId();
        $resource->name = $goldForm->getName();

        return $resource;
    }
}
