<?php

declare(strict_types=1);

namespace App\ApiResource\Input;

use ApiPlatform\Metadata\ApiProperty;
use App\Validator\NoNullCharacter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a client sends to add a contractor (POST). The name may be missing or null here: the validation then says why,
 * with a code the clients translate. What the API answers is ContractorResource.
 */
final class ContractorInput
{
    #[ApiProperty(
        description: 'Contractor name. Must be unique. Required, 1-255 characters.',
        example: 'ACME Sp. z o.o.',
    )]
    #[Assert\NotBlank(message: 'contractor.name_not_blank', normalizer: 'trim')]
    #[Assert\Length(max: 255, maxMessage: 'contractor.name_max_length')]
    #[NoNullCharacter]
    public ?string $name = null;
}
