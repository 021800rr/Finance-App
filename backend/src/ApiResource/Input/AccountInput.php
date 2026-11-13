<?php

declare(strict_types=1);

namespace App\ApiResource\Input;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\Account;
use App\Validator\TrueOrOmitted;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a client sends to change an account (PATCH): whether it is hidden and whether it is the main one, the only
 * changes the web app allows. PATCH lays the request over the current values, so a field left out stays as it is.
 * What the API answers is AccountResource.
 */
final class AccountInput
{
    private const string IS_MAIN_DESCRIPTION =
        'Send true to make this the main account; the previous main account is unset automatically. '
        .'Leave this field out to keep the current state.';

    #[ApiProperty(
        description: 'Whether the account is hidden from the summary/list views. A hidden account cannot be chosen '
        .'for a new operation or transfer; its existing operations can still be updated.',
        example: false,
    )]
    #[Assert\NotNull(message: 'account.hidden_not_null')]
    public ?bool $hidden = null;

    // Not ?bool: a value of another type has to reach TrueOrOmitted instead of ending as a generic 400.
    #[ApiProperty(
        description: self::IS_MAIN_DESCRIPTION,
        example: true,
        schema: ['type' => 'boolean', 'description' => self::IS_MAIN_DESCRIPTION, 'example' => true],
    )]
    #[TrueOrOmitted]
    public mixed $isMain = null;

    /**
     * The current values of an account, for PATCH to lay the request over.
     */
    public static function fromEntity(Account $account): self
    {
        $input = new self();
        $input->hidden = $account->isHidden();
        $input->isMain = $account->isMain();

        return $input;
    }
}
