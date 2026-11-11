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
use App\ApiResource\Input\ContractorInput;
use App\Entity\Contractor;
use App\State\ContractorProcessor;
use App\State\ContractorProvider;
use App\Validator\NoNullCharacter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A contractor as the API returns it: every field is always there, and the OpenAPI document says so (a generated
 * client gets types without needless nulls). What a client sends is ContractorInput (input: on POST).
 */
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
            description: 'Creates a new contractor. Fails with 409 and errorCode "contractor.name_not_unique" if a '
            .'contractor with this name already exists.',
            input: ContractorInput::class,
            processor: ContractorProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final readonly class ContractorResource
{
    public function __construct(
        #[ApiProperty(description: 'Contractor id.', required: true, example: 1)]
        public int $id,
        #[ApiProperty(description: 'Contractor name, unique.', required: true, example: 'ACME Sp. z o.o.')]
        public string $name,
    ) {
    }

    public static function fromEntity(Contractor $contractor): self
    {
        return new self($contractor->getId(), $contractor->getName());
    }

    /**
     * @param array{id: int, name: string} $row
     */
    public static function fromArray(array $row): self
    {
        return new self($row['id'], $row['name']);
    }
}
