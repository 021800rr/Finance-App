<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Entity\AccountOperation;
use App\State\TransferProcessor;
use App\Util\BusinessDateRange;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Transfer',
    description: 'Transfer/exchange between two of the user\'s own accounts. Creates two linked '
    .'AccountOperation records (debit + credit) sharing the same transactionUuid, exactly like '
    .'the "Transfer / Exchange" form. The contractor is always the fixed "Przelew własny" contractor.',
    operations: [
        new Post(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Creates a transfer. If fromAccount and toAccount have the same currency, '
            .'amountTo may be omitted (it defaults to amountFrom). If currencies differ, '
            .'amountTo is required.',
            processor: TransferProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class TransferResource
{
    #[ApiProperty(description: 'Id of the account being debited. Required.', example: 1)]
    #[Assert\NotNull(message: 'transfer.from_account_required')]
    public ?int $fromAccountId = null;

    #[ApiProperty(description: 'Id of the account being credited. Required, must differ from fromAccountId.', example: 2)]
    #[Assert\NotNull(message: 'transfer.to_account_required')]
    public ?int $toAccountId = null;

    #[ApiProperty(description: 'Transfer date, format YYYY-MM-DD, from '.BusinessDateRange::MIN_DATE.' to '
        .BusinessDateRange::MAX_DATE.' inclusive. Required.', example: '2026-09-29')]
    #[Assert\NotBlank(message: 'common.date_not_blank')]
    public ?string $operationDate = null;

    #[ApiProperty(
        description: 'Positive amount debited from fromAccount, as a decimal string, e.g. "100.00". Required.',
        example: '100.00',
    )]
    #[Assert\NotBlank(message: 'transfer.amount_from_required')]
    public ?string $amountFrom = null;

    #[ApiProperty(
        description: 'Positive amount credited to toAccount, as a decimal string. Required only when fromAccount '
        .'and toAccount use different currencies; otherwise omit it (defaults to amountFrom).',
        example: null,
    )]
    public ?string $amountTo = null;

    #[ApiProperty(description: 'Free-text note. Optional.', example: 'Monthly savings transfer')]
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
