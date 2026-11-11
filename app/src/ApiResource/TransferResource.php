<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\ApiResource\Input\TransferInput;
use App\Entity\AccountOperation;
use App\OpenApi\ErrorResponseContent;
use App\State\TransferProcessor;

/**
 * A transfer as the API answers it: the two operations it created, debit and credit, linked by one transaction UUID.
 * Every field is always there (the description only when there is one), and the OpenAPI document says so. What a
 * client sends is TransferInput (input: on POST), where any field may be missing until the validation says why.
 */
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
                    .'Other refused input checked only on saving, e.g. an amount written as "1e2", has errorCode '
                    .'"http.unprocessable_content".',
                    content: new \ArrayObject(ErrorResponseContent::VALIDATION_OR_BUSINESS_ERROR),
                )],
                security: [['JWT' => []]],
            ),
            description: 'Creates a transfer. If fromAccount and toAccount have the same currency, '
            .'amountTo is generated automatically and must not be sent. If currencies differ, '
            .'amountTo is required. A hidden account cannot be chosen on either side: such a request is '
            .'rejected with 422 on fromAccountId or toAccountId.',
            input: TransferInput::class,
            processor: TransferProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final readonly class TransferResource
{
    public function __construct(
        #[ApiProperty(description: 'Id of the account debited.', required: true, example: 1)]
        public int $fromAccountId,
        #[ApiProperty(description: 'Id of the account credited.', required: true, example: 2)]
        public int $toAccountId,
        #[ApiProperty(description: 'Transfer date, format YYYY-MM-DD.', required: true, example: '2026-09-29')]
        public string $operationDate,
        #[ApiProperty(description: 'Free-text note; left out when there is none.', example: 'Monthly savings transfer')]
        public ?string $description,
        #[ApiProperty(
            description: 'Shared id linking the debit and credit operations.',
            required: true,
            example: '01a1c8b2-262e-72a9-9ac6-1652e5dffefe',
        )]
        public string $transactionUuid,
        #[ApiProperty(description: 'Id of the created debit (out) operation.', required: true, example: 101)]
        public int $outOperationId,
        #[ApiProperty(
            description: 'Debited amount as recorded, negative decimal string.',
            required: true,
            example: '-100.00',
        )]
        public string $outAmount,
        #[ApiProperty(description: 'Currency of the debit operation.', required: true, example: 'PLN')]
        public string $outCurrencyCode,
        #[ApiProperty(description: 'Id of the created credit (in) operation.', required: true, example: 102)]
        public int $inOperationId,
        #[ApiProperty(
            description: 'Credited amount as recorded, positive decimal string.',
            required: true,
            example: '23.00',
        )]
        public string $inAmount,
        #[ApiProperty(description: 'Currency of the credit operation.', required: true, example: 'CHF')]
        public string $inCurrencyCode,
    ) {
    }

    public static function fromOperations(AccountOperation $out, AccountOperation $in): self
    {
        return new self(
            fromAccountId: $out->getAccount()->getId(),
            toAccountId: $in->getAccount()->getId(),
            operationDate: $out->getOperationDate()->format('Y-m-d'),
            description: $out->getDescription(),
            transactionUuid: $out->getTransactionUuid()?->toRfc4122()
                ?? throw new \LogicException('The two operations of a transfer always share a transaction UUID.'),
            outOperationId: $out->getId(),
            outAmount: $out->getAmountAsMoney()->toDecimalString(),
            outCurrencyCode: $out->getCurrency()->getCode(),
            inOperationId: $in->getId(),
            inAmount: $in->getAmountAsMoney()->toDecimalString(),
            inCurrencyCode: $in->getCurrency()->getCode(),
        );
    }
}
