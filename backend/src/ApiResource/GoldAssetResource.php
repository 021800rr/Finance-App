<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiResource\Input\GoldAssetInput;
use App\Entity\GoldAsset;
use App\State\GoldAssetProcessor;
use App\State\GoldAssetProvider;

/**
 * A gold asset as the API returns it: every field is always there, and the OpenAPI document says so (a generated
 * client gets types without needless nulls). What a client sends is GoldAssetInput (input: on POST and PATCH), where
 * any field may be missing until the validation says why.
 */
#[ApiResource(
    shortName: 'GoldAsset',
    description: 'A physical gold holding (form, ounces, description). '
    .'Its value over time is tracked by GoldPrice entries.',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Lists all gold assets, ordered by id.',
            provider: GoldAssetProvider::class,
        ),
        new Get(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Retrieves a single gold asset by id.',
            provider: GoldAssetProvider::class,
        ),
        new Post(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Creates a new gold asset (formId, description, ounces).',
            input: GoldAssetInput::class,
            processor: GoldAssetProcessor::class,
        ),
        new Patch(
            openapi: new OpenApiOperation(
                summary: 'Updates a gold asset.',
                description: 'Updates the form, ounces or description, as in the web edit form. '
                .'Omitted fields keep their current values. Its price history is preserved.',
                security: [['JWT' => []]],
            ),
            input: GoldAssetInput::class,
            provider: GoldAssetProvider::class,
            processor: GoldAssetProcessor::class,
        ),
        new Delete(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Deletes a gold asset. Its price history (GoldPrice entries) is deleted along with it.',
            provider: GoldAssetProvider::class,
            processor: GoldAssetProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final readonly class GoldAssetResource
{
    public function __construct(
        #[ApiProperty(description: 'Gold asset id.', required: true, example: 1)]
        public int $id,
        #[ApiProperty(description: 'Id of the gold form.', required: true, example: 1)]
        public int $formId,
        #[ApiProperty(description: 'Name of the gold form.', required: true, example: 'Sztabka')]
        public string $formName,
        #[ApiProperty(description: 'Free-text description.', required: true, example: 'Sztabka 1oz')]
        public string $description,
        #[ApiProperty(
            description: 'Weight in ounces as a decimal string with 4 decimal places, e.g. "1.5000".',
            required: true,
            example: '1.0000',
        )]
        public string $ounces,
    ) {
    }

    public static function fromEntity(GoldAsset $goldAsset): self
    {
        return new self(
            id: $goldAsset->getId(),
            formId: $goldAsset->getForm()->getId(),
            formName: $goldAsset->getForm()->getName(),
            description: $goldAsset->getDescription(),
            ounces: $goldAsset->getOuncesAsQuantity()->toDecimalString(),
        );
    }
}
