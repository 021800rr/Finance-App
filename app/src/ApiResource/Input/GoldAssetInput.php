<?php

declare(strict_types=1);

namespace App\ApiResource\Input;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Validator\DecimalString;
use App\Validator\NoNullCharacter;
use App\Validator\RecordExists;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a client sends to create a gold asset (POST) or to change one (PATCH). Any field may be missing or null here:
 * the validation then says which one and why, with a code the clients translate. What the API answers is
 * GoldAssetResource, where every field is always there.
 */
final class GoldAssetInput
{
    #[ApiProperty(description: 'Id of an existing gold form. Required.', example: 1)]
    #[Assert\NotNull(message: 'gold_asset.form_not_null')]
    #[RecordExists(GoldForm::class, message: 'gold_form.not_found')]
    public ?int $formId = null;

    #[ApiProperty(description: 'Free-text description. Required, at most 255 characters.', example: 'Sztabka 1oz')]
    #[Assert\NotBlank(message: 'gold_asset.description_not_blank', normalizer: 'trim')]
    #[Assert\Length(max: 255, maxMessage: 'gold_asset.description_max_length', normalizer: 'trim')]
    #[NoNullCharacter]
    public ?string $description = null;

    #[ApiProperty(
        description: 'Weight in ounces as a decimal string, up to 4 decimal places, e.g. "1.5000". '
        .'Required, 0.0001-999.9999.',
        example: '1.0000',
    )]
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'gold_asset.ounces_not_blank', normalizer: 'trim'),
        new DecimalString(),
        new Assert\Range(
            notInRangeMessage: 'gold_asset.ounces_out_of_range',
            invalidMessage: 'common.numeric_invalid',
            min: 0.0001,
            max: 999.9999,
        ),
    ])]
    public ?string $ounces = null;

    /**
     * The current values of a gold asset, for PATCH to lay the request over: what the request leaves out stays.
     */
    public static function fromEntity(GoldAsset $goldAsset): self
    {
        $input = new self();
        $input->formId = $goldAsset->getForm()->getId();
        $input->description = $goldAsset->getDescription();
        $input->ounces = $goldAsset->getOuncesAsQuantity()->toDecimalString();

        return $input;
    }
}
