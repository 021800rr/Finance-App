<?php

declare(strict_types=1);

namespace App\ApiResource\Input;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\Account;
use App\Validator\RecordExists;
use App\Validator\SelectableAccount;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a client sends to create an operation (POST): what a change can send, and the account the operation goes to.
 * The account is chosen once: a change (AccountOperationInput) has no such field, and one sent anyway is ignored.
 */
final class NewAccountOperationInput extends AccountOperationInput
{
    #[ApiProperty(
        description: 'Id of the account this operation belongs to. Required; a hidden account is rejected.',
        example: 1,
    )]
    #[Assert\NotNull(message: 'operation.account_required')]
    #[RecordExists(Account::class, message: 'account.not_found')]
    #[SelectableAccount]
    public ?int $accountId = null;
}
