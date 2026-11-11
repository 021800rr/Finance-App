<?php

declare(strict_types=1);

namespace App\ApiResource\Input;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\Account;
use App\Util\BusinessDateRange;
use App\Validator\BusinessDate;
use App\Validator\DecimalString;
use App\Validator\NoNullCharacter;
use App\Validator\RecordExists;
use App\Validator\SelectableAccount;
use App\Validator\TransferAmountTo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a client sends to transfer money between two of its accounts (POST). Any field may be missing or null here:
 * the validation then says which one and why, with a code the clients translate. What the API answers is
 * TransferResource: the two operations the transfer created.
 */
final class TransferInput
{
    #[ApiProperty(description: 'Id of the account being debited. Required; a hidden account is rejected.', example: 1)]
    #[Assert\NotNull(message: 'transfer.from_account_required')]
    #[RecordExists(Account::class, message: 'account.not_found')]
    #[SelectableAccount]
    public ?int $fromAccountId = null;

    #[ApiProperty(
        description: 'Id of the account being credited. Required, must differ from fromAccountId; '
        .'a hidden account is rejected.',
        example: 2,
    )]
    #[Assert\NotNull(message: 'transfer.to_account_required')]
    #[RecordExists(Account::class, message: 'account.not_found')]
    #[SelectableAccount]
    public ?int $toAccountId = null;

    #[ApiProperty(description: 'Transfer date, format YYYY-MM-DD, from '.BusinessDateRange::MIN_DATE.' to '
        .BusinessDateRange::MAX_DATE.' inclusive. Required.', example: '2026-09-29')]
    #[Assert\NotBlank(message: 'common.date_not_blank')]
    #[BusinessDate]
    public ?string $operationDate = null;

    #[ApiProperty(
        description: 'Positive amount debited from fromAccount, as a decimal string, e.g. "100.00". Required.',
        example: '100.00',
    )]
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'transfer.amount_from_required', normalizer: 'trim'),
        new DecimalString(),
        new Assert\Range(
            notInRangeMessage: 'transfer.amount_out_of_range',
            invalidMessage: 'common.numeric_invalid',
            min: 0.01,
            max: 999999.99,
        ),
    ])]
    public ?string $amountFrom = null;

    #[ApiProperty(
        description: 'Positive amount credited to toAccount, as a decimal string. Required when fromAccount and '
        .'toAccount use different currencies. With one currency it is generated automatically: do not send this field.',
        schema: ['type' => 'string'],
        example: '23.00',
    )]
    // Not typed as ?string: with one currency a value of any type must get the same clear error, not a generic 400.
    #[TransferAmountTo]
    public mixed $amountTo = null;

    #[ApiProperty(
        description: 'Free-text note. Optional, at most 255 characters after trimming. Surrounding spaces are removed; '
        .'a blank note is stored as null.',
        example: 'Monthly savings transfer',
    )]
    #[Assert\Length(max: 255, normalizer: 'trim', maxMessage: 'common.description_max_length')]
    #[NoNullCharacter]
    public ?string $description = null;
}
