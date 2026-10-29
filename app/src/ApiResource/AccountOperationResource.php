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
use App\Entity\AccountOperation;
use App\Service\Paginator;
use App\State\AccountOperationProcessor;
use App\State\AccountOperationProvider;
use App\Util\BusinessDateRange;
use App\Validator\SelectableAccount;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'AccountOperation',
    description: 'A single account transaction (income or expense).',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Searches the operations of all accounts, newest first, '.Paginator::DEFAULT_PER_PAGE.' per page. '
            .'The search text q is required. To list the operations of one account use /accounts/{accountId}/operations.',
            provider: AccountOperationProvider::class,
            parameters: ['q' => new QueryParameter(
                property: 'description',
                description: 'Search in the description and the contractor name (case-insensitive, partial match) '
                .'or by exact amount (e.g. 150.50). Min 2 chars.',
                required: true,
                constraints: [
                    new Assert\NotBlank(normalizer: 'trim'),
                    new Assert\Length(min: 2),
                ]
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
            .'Responds with 404 when the account does not exist.',
            provider: AccountOperationProvider::class,
        ),
        new Get(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Retrieves a single operation by id.',
            provider: AccountOperationProvider::class,
        ),
        new Post(
            openapi: new OpenApiOperation(
                responses: ['422' => new OpenApiResponse(
                    description: 'Validation failed, e.g. accountId points to a hidden account.',
                )],
                security: [['JWT' => []]],
            ),
            description: 'Creates a new operation. Either contractorId or contractorName must be given; '
            .'if contractorName does not match an existing contractor, one is created automatically. '
            .'A hidden account cannot be chosen: such a request is rejected with 422 on accountId.',
            denormalizationContext: ['groups' => ['operation:create', 'operation:write']],
            validationContext: ['groups' => ['Default', 'operation:create']],
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
final class AccountOperationResource
{
    #[ApiProperty(description: 'Operation id. Read-only, ignored on write.', writable: false, example: 1)]
    public ?int $id = null;

    #[ApiProperty(
        description: 'Id of the account this operation belongs to. Required on create; a hidden account is rejected; '
        .'it cannot be changed afterwards, so it is not part of the update request.',
        example: 1
    )]
    #[Assert\NotNull(message: 'operation.account_required')]
    #[SelectableAccount(groups: ['operation:create'])]
    #[Groups(['operation:create'])]
    public ?int $accountId = null;

    #[ApiProperty(
        description: 'Id of an existing contractor. Provide this OR contractorName, not both '
        .'(leave this null when using contractorName).',
        example: null,
    )]
    #[Groups(['operation:write'])]
    public ?int $contractorId = null;

    #[ApiProperty(
        description: 'Contractor name. Used to find an existing contractor by name, or create a new one '
        .'if none matches. Ignored if contractorId is set.',
        example: 'ACME Sp. z o.o.',
    )]
    #[Groups(['operation:write'])]
    public ?string $contractorName = null;

    #[ApiProperty(description: 'Operation date, format YYYY-MM-DD, from '.BusinessDateRange::MIN_DATE.' to '
        .BusinessDateRange::MAX_DATE.' inclusive. Required.', example: '2026-09-29')]
    #[Assert\NotBlank(message: 'operation.date_not_blank')]
    #[Groups(['operation:write'])]
    public ?string $operationDate = null;

    #[ApiProperty(
        description: 'Amount as a decimal string, e.g. "50.00". Positive for income, negative for expense '
        .'(exact sign convention follows the same rule as the Twig form). Required.',
        example: '50.00',
    )]
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'operation.amount_not_blank', normalizer: 'trim'),
        new Assert\Range(
            notInRangeMessage: 'common.amount_out_of_range',
            invalidMessage: 'common.numeric_invalid',
            min: -999999.99,
            max: 999999.99,
        ),
    ])]
    #[Groups(['operation:write'])]
    public ?string $amount = null;

    #[ApiProperty(
        description: 'Currency code. Read-only, derived from the account\'s currency, ignored on write.',
        writable: false,
        example: 'PLN',
    )]
    public ?string $currencyCode = null;

    #[ApiProperty(
        description: 'Free-text note. Optional, at most 255 characters after trimming. Surrounding spaces are removed; '
        .'a blank note is stored as null.',
        example: 'Rent for September',
    )]
    #[Assert\Length(max: 255, normalizer: 'trim', maxMessage: 'common.description_max_length')]
    #[Groups(['operation:write'])]
    public ?string $description = null;

    #[ApiProperty(description: 'Whether the operation is confirmed. Defaults to false.', example: false)]
    #[Groups(['operation:write'])]
    public bool $confirmed = false;

    #[ApiProperty(description: 'Transaction UUID. Read-only, ignored on write.', writable: false, example: null)]
    public ?string $transactionUuid = null;

    public static function fromEntity(AccountOperation $accountOperation): self
    {
        $resource = new self();
        $resource->id = $accountOperation->getId();
        $resource->accountId = $accountOperation->getAccount()->getId();
        $resource->contractorId = $accountOperation->getContractor()->getId();
        $resource->contractorName = $accountOperation->getContractor()->getName();
        $resource->operationDate = $accountOperation->getOperationDate()->format('Y-m-d');
        $resource->amount = $accountOperation->getAmountAsMoney()->toDecimalString();
        $resource->currencyCode = $accountOperation->getCurrency()->getCode();
        $resource->description = $accountOperation->getDescription();
        $resource->confirmed = $accountOperation->isConfirmed();
        $resource->transactionUuid = $accountOperation->getTransactionUuid()?->toRfc4122();

        return $resource;
    }
}
