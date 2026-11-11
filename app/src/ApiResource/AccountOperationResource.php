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
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\ApiResource\Input\AccountOperationInput;
use App\Entity\AccountOperation;
use App\Service\Paginator;
use App\State\AccountOperationOtherSideProvider;
use App\State\AccountOperationProcessor;
use App\State\AccountOperationProvider;
use App\Validator\NoNullCharacter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An operation as the API returns it: every field is always there, and the OpenAPI document says so (a generated
 * client gets types without needless nulls); the description and the transaction UUID are left out when there are
 * none. What a client sends is AccountOperationInput (input: on POST and PATCH), where any field may be missing until
 * the validation says why.
 */
#[ApiResource(
    shortName: 'AccountOperation',
    description: 'A single account transaction (income or expense).',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Searches the operations of all accounts, newest first, '.Paginator::DEFAULT_PER_PAGE
            .' per page. The search text q is required; page must be a positive whole number. '
            .'To list the operations of one account use /accounts/{accountId}/operations.',
            provider: AccountOperationProvider::class,
            parameters: ['q' => new QueryParameter(
                property: 'description',
                description: 'Search in the description and the contractor name (case-insensitive, partial match) '
                .'or by exact amount (e.g. 150.50). 1-255 characters after trimming.',
                required: true,
                constraints: [new Assert\Sequentially([
                    new Assert\NotBlank(normalizer: 'trim'),
                    new Assert\Length(min: 1, max: 255, normalizer: 'trim'),
                    new NoNullCharacter(),
                ])]
            )],
        ),
        new GetCollection(
            uriTemplate: '/accounts/{accountId}/operations',
            uriVariables: ['accountId'],
            openapi: new OpenApiOperation(
                responses: ['404' => new OpenApiResponse(description: 'The account does not exist.')],
                security: [['JWT' => []]],
            ),
            description: 'Lists the operations of one account, newest first, '.Paginator::DEFAULT_PER_PAGE.' per page. '
            .'Responds with 404 when the account does not exist, and with 422 when page is not a positive '
            .'whole number.',
            provider: AccountOperationProvider::class,
        ),
        new Get(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Retrieves a single operation by id.',
            provider: AccountOperationProvider::class,
        ),
        new Get(
            uriTemplate: '/account_operations/{id}/other-side',
            openapi: new OpenApiOperation(
                summary: 'Retrieves the other operation of a transfer.',
                responses: ['404' => new OpenApiResponse(
                    description: 'The operation does not exist (errorCode account_operation.not_found), or it has '
                    .'no other side: it is not part of a transfer, or the other operation was deleted '
                    .'(errorCode account_operation.other_side_not_found).',
                )],
                security: [['JWT' => []]],
            ),
            description: 'The other operation of the transfer the operation belongs to, the one with the same '
            .'transactionUuid, as the web app\'s 🔗 link finds it.',
            provider: AccountOperationOtherSideProvider::class,
        ),
        new Post(
            openapi: new OpenApiOperation(
                responses: ['422' => new OpenApiResponse(
                    description: 'Validation failed, e.g. accountId points to a hidden account.',
                )],
                security: [['JWT' => []]],
            ),
            description: 'Creates a new operation. Give contractorId, contractorName or both (of the same contractor); '
            .'a contractorName that matches no contractor creates one. '
            .'A hidden account cannot be chosen: such a request is rejected with 422 on accountId.',
            denormalizationContext: ['groups' => ['operation:create', 'operation:write']],
            validationContext: ['groups' => ['Default', 'operation:create']],
            input: AccountOperationInput::class,
            processor: AccountOperationProcessor::class,
        ),
        new Patch(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Updates an existing operation. Send the contractor as in POST, but only when it changes: a '
            .'contractorId or a contractorName that differs from the current contractor assigns the operation to that '
            .'contractor (created when the name is new). '
            .'The account of an operation is fixed at creation and cannot be changed. '
            .'Operations of an account that was hidden later can still be updated.',
            denormalizationContext: ['groups' => ['operation:write']],
            input: AccountOperationInput::class,
            provider: AccountOperationProvider::class,
            processor: AccountOperationProcessor::class,
        ),
        new Delete(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Deletes an operation.',
            provider: AccountOperationProvider::class,
            processor: AccountOperationProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final readonly class AccountOperationResource
{
    private const string DESCRIPTION = 'Free-text note; left out when there is none.';
    private const string TRANSACTION_UUID =
        'Id shared with the other operation of a transfer; left out for an operation that is not part of one.';

    public function __construct(
        #[ApiProperty(description: 'Operation id.', required: true, example: 1)]
        public int $id,
        #[ApiProperty(description: 'Id of the account this operation belongs to.', required: true, example: 1)]
        public int $accountId,
        #[ApiProperty(description: 'Id of the contractor.', required: true, example: 10)]
        public int $contractorId,
        #[ApiProperty(description: 'Name of the contractor.', required: true, example: 'Example contractor')]
        public string $contractorName,
        #[ApiProperty(description: 'Operation date, format YYYY-MM-DD.', required: true, example: '2026-09-29')]
        public string $operationDate,
        #[ApiProperty(
            description: 'Amount as a decimal string: positive for income, negative for an expense.',
            required: true,
            example: '-50.00',
        )]
        public string $amount,
        #[ApiProperty(description: 'Currency code, the one of the account.', required: true, example: 'PLN')]
        public string $currencyCode,
        // Left out when absent, never sent as null: the schema says string, not string or null.
        #[ApiProperty(
            description: self::DESCRIPTION,
            example: 'Rent for September',
            schema: ['type' => 'string', 'description' => self::DESCRIPTION, 'example' => 'Rent for September'],
        )]
        public ?string $description,
        #[ApiProperty(description: 'Whether the operation is confirmed.', required: true, example: false)]
        public bool $confirmed,
        #[ApiProperty(
            description: self::TRANSACTION_UUID,
            example: '01a17299-9aed-7df8-ba39-d49647205525',
            schema: [
                'type' => 'string',
                'description' => self::TRANSACTION_UUID,
                'example' => '01a17299-9aed-7df8-ba39-d49647205525',
            ],
        )]
        public ?string $transactionUuid,
    ) {
    }

    public static function fromEntity(AccountOperation $accountOperation): self
    {
        return new self(
            id: $accountOperation->getId(),
            accountId: $accountOperation->getAccount()->getId(),
            contractorId: $accountOperation->getContractor()->getId(),
            contractorName: $accountOperation->getContractor()->getName(),
            operationDate: $accountOperation->getOperationDate()->format('Y-m-d'),
            amount: $accountOperation->getAmountAsMoney()->toDecimalString(),
            currencyCode: $accountOperation->getCurrency()->getCode(),
            description: $accountOperation->getDescription(),
            confirmed: $accountOperation->isConfirmed(),
            transactionUuid: $accountOperation->getTransactionUuid()?->toRfc4122(),
        );
    }
}
