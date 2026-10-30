<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Entity\AccountOperation;
use App\OpenApi\ErrorResponseContent;
use App\State\TransferProcessor;
use App\Util\BusinessDateRange;
use App\Validator\BusinessDate;
use App\Validator\NoNullCharacter;
use App\Validator\SelectableAccount;
use App\Validator\TransferAmountTo;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Transfer',
    description: 'Transfer/exchange between two of the user\'s own accounts. Creates two linked '
    .'AccountOperation records (debit + credit) sharing the same transactionUuid, exactly like '
    .'the "Transfer / Exchange" form. The contractor is always the fixed "Przelew własny" contractor.',
    operations: [
        new Post(
            openapi: new OpenApiOperation(
                responses: ['422' => new OpenApiResponse(
                    description: 'Either validation failed (errorCode "validation.failed", with a "violations" list), '
                    .'e.g. fromAccountId or toAccountId points to a hidden account; or the transfer was refused '
                    .'(no "violations"): errorCode "transfer.accounts_must_differ" or '
                    .'"transfer.amount_to_required_for_currency_exchange" (params fromCurrency, toCurrency). '
                    .'Other refused input checked only on saving, e.g. operationDate outside the allowed range or '
                    .'an amount that cannot be read, has errorCode "http.unprocessable_content".',
                    content: new \ArrayObject(ErrorResponseContent::VALIDATION_OR_BUSINESS_ERROR),
                )],
                security: [['JWT' => []]],
            ),
            description: 'Creates a transfer. If fromAccount and toAccount have the same currency, '
            .'amountTo is ignored, whatever it contains: the credited amount equals amountFrom. If currencies differ, '
            .'amountTo is required. A hidden account cannot be chosen on either side: such a request is '
            .'rejected with 422 on fromAccountId or toAccountId.',
            processor: TransferProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class TransferResource
{
    #[ApiProperty(description: 'Id of the account being debited. Required; a hidden account is rejected.', example: 1)]
    #[Assert\NotNull(message: 'transfer.from_account_required')]
    #[SelectableAccount]
    public ?int $fromAccountId = null;

    #[ApiProperty(
        description: 'Id of the account being credited. Required, must differ from fromAccountId; '
        .'a hidden account is rejected.',
        example: 2,
    )]
    #[Assert\NotNull(message: 'transfer.to_account_required')]
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
        new Assert\Range(
            notInRangeMessage: 'transfer.amount_out_of_range',
            invalidMessage: 'common.numeric_invalid',
            min: 0.01,
            max: 999999.99,
        ),
    ])]
    public ?string $amountFrom = null;

    #[ApiProperty(
        description: 'Positive amount credited to toAccount, as a decimal string. Required only when fromAccount '
        .'and toAccount use different currencies; with one currency it is ignored, whatever it contains.',
        schema: ['type' => ['string', 'null']],
        example: null,
    )]
    // Not typed as ?string: with one currency a value of any type must be ignored, not refused while reading the body.
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

    #[ApiProperty(description: 'Shared id linking the debit and credit operations. Read-only.', writable: false, example: null)]
    public ?string $transactionUuid = null;

    #[ApiProperty(description: 'Id of the created debit (out) operation. Read-only.', writable: false, example: null)]
    public ?int $outOperationId = null;

    #[ApiProperty(description: 'Debited amount as recorded, negative decimal string. Read-only.', writable: false, example: null)]
    public ?string $outAmount = null;

    #[ApiProperty(description: 'Currency of the debit operation. Read-only.', writable: false, example: null)]
    public ?string $outCurrencyCode = null;

    #[ApiProperty(description: 'Id of the created credit (in) operation. Read-only.', writable: false, example: null)]
    public ?int $inOperationId = null;

    #[ApiProperty(description: 'Credited amount as recorded, positive decimal string. Read-only.', writable: false, example: null)]
    public ?string $inAmount = null;

    #[ApiProperty(description: 'Currency of the credit operation. Read-only.', writable: false, example: null)]
    public ?string $inCurrencyCode = null;

    public static function fromOperations(AccountOperation $out, AccountOperation $in): self
    {
        $resource = new self();
        $resource->fromAccountId = $out->getAccount()->getId();
        $resource->toAccountId = $in->getAccount()->getId();
        $resource->operationDate = $out->getOperationDate()->format('Y-m-d');
        $resource->description = $out->getDescription();
        $resource->transactionUuid = $out->getTransactionUuid()?->toRfc4122();
        $resource->outOperationId = $out->getId();
        $resource->outAmount = $out->getAmountAsMoney()->toDecimalString();
        $resource->outCurrencyCode = $out->getCurrency()->getCode();
        $resource->inOperationId = $in->getId();
        $resource->inAmount = $in->getAmountAsMoney()->toDecimalString();
        $resource->inCurrencyCode = $in->getCurrency()->getCode();

        return $resource;
    }
}
