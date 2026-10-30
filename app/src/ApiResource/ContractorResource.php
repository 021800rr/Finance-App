<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Entity\Contractor;
use App\State\ContractorProcessor;
use App\State\ContractorProvider;
use App\Validator\NoNullCharacter;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Contractor',
    description: 'A contractor (payee/payer) used on account operations and fixed expenses.',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Lists contractors, optionally filtered by a partial name match.',
            provider: ContractorProvider::class,
            parameters: [
                'name' => new QueryParameter(
                    description: 'Partial, case-insensitive name match (e.g. "Éc" matches "Écolier"). '
                    .'1-255 characters after trimming. Exact matches come first, then names starting with the text.',
                    constraints: [new Assert\Length(min: 1, max: 255, normalizer: 'trim'), new NoNullCharacter()],
                ),
            ],
        ),
        new Get(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Retrieves a single contractor by id.',
            provider: ContractorProvider::class,
        ),
        new Post(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Creates a new contractor. Fails with 409 if a contractor with this name already exists.',
            processor: ContractorProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class ContractorResource
{
    #[ApiProperty(description: 'Contractor id. Read-only, ignored on write.', writable: false, example: 1)]
    public ?int $id = null;

    #[ApiProperty(
        description: 'Contractor name. Must be unique. Required, 1-255 characters.',
        example: 'ACME Sp. z o.o.',
    )]
    #[Assert\NotBlank(message: 'contractor.name_not_blank', normalizer: 'trim')]
    #[Assert\Length(max: 255, maxMessage: 'contractor.name_max_length')]
    #[NoNullCharacter]
    public ?string $name = null;

    public static function fromEntity(Contractor $contractor): self
    {
        $resource = new self();
        $resource->id = $contractor->getId();
        $resource->name = $contractor->getName();

        return $resource;
    }

    /**
     * @param array{id: int, name: string} $row
     */
    public static function fromArray(array $row): self
    {
        $resource = new self();
        $resource->id = $row['id'];
        $resource->name = $row['name'];

        return $resource;
    }
}
