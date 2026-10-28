<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Entity\GoldAsset;
use App\State\GoldAssetProcessor;
use App\State\GoldAssetProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'GoldAsset',
    description: 'A physical gold holding (form, ounces, description). Its value over time is tracked by GoldPrice entries.',
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
final class GoldAssetResource
{
    #[ApiProperty(description: 'Gold asset id. Read-only, ignored on write.', writable: false, example: 1)]
    public ?int $id = null;

    #[ApiProperty(description: 'Id of an existing gold form. Required.', example: 1)]
    #[Assert\NotNull(message: 'gold_asset.form_not_null')]
    public ?int $formId = null;

    #[ApiProperty(description: 'Gold form name. Read-only, ignored on write.', writable: false, example: 'Sztabka')]
    public ?string $formName = null;

    #[ApiProperty(description: 'Free-text description. Required.', example: 'Sztabka 1oz')]
    #[Assert\NotBlank(message: 'gold_asset.description_not_blank', normalizer: 'trim')]
    public ?string $description = null;

    #[ApiProperty(
        description: 'Weight in ounces as a decimal string, up to 4 decimal places, e.g. "1.5000". Required, 0.0001-999.9999.',
        example: '1.0000',
    )]
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'gold_asset.ounces_must_be_positive', normalizer: 'trim'),
        new Assert\Range(
            min: 0.0001,
            max: 999.9999,
            notInRangeMessage: 'gold_asset.ounces_out_of_range',
            invalidMessage: 'common.numeric_invalid',
        ),
    ])]
    public ?string $ounces = null;

    public static function fromEntity(GoldAsset $goldAsset): self
    {
        $resource = new self();
        $resource->id = $goldAsset->getId();
        $resource->formId = $goldAsset->getForm()->getId();
        $resource->formName = $goldAsset->getForm()->getName();
        $resource->description = $goldAsset->getDescription();
        $resource->ounces = $goldAsset->getOuncesAsQuantity()->toDecimalString();

        return $resource;
    }
}
