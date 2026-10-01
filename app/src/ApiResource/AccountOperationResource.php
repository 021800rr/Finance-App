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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Entity\AccountOperation;
use App\State\AccountOperationProcessor;
use App\State\AccountOperationProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'AccountOperation',
    description: 'A single account transaction (income or expense).',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Lists operations. Supports ?accountId=<id> to filter by account and ?page=<n> for pagination.',
            provider: AccountOperationProvider::class,
        ),
        new Get(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Retrieves a single operation by id.',
            provider: AccountOperationProvider::class,
        ),
        new Post(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Creates a new operation. Either contractorId or contractorName must be given; '
            .'if contractorName does not match an existing contractor, one is created automatically.',
            processor: AccountOperationProcessor::class,
        ),
        new Patch(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Updates an existing operation. Same rules as POST for contractorId/contractorName.',
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

    #[ApiProperty(description: 'Id of the account this operation belongs to. Required.', example: 1)]
    #[Assert\NotNull(message: 'operation.account_required')]
    public ?int $accountId = null;

    #[ApiProperty(
        description: 'Id of an existing contractor. Provide this OR contractorName, not both '
        .'(leave this null when using contractorName).',
        example: null,
    )]
    public ?int $contractorId = null;

    #[ApiProperty(
        description: 'Contractor name. Used to find an existing contractor by name, or create a new one '
        .'if none matches. Ignored if contractorId is set.',
        example: 'ACME Sp. z o.o.',
    )]
    public ?string $contractorName = null;

    #[ApiProperty(description: 'Operation date, format YYYY-MM-DD. Required.', example: '2026-09-29')]
    #[Assert\NotBlank(message: 'operation.date_not_blank')]
    public ?string $operationDate = null;

    #[ApiProperty(
        description: 'Amount as a decimal string, e.g. "50.00". Positive for income, negative for expense '
        .'(exact sign convention follows the same rule as the Twig form). Required.',
        example: '50.00',
    )]
    #[Assert\NotBlank(message: 'operation.amount_not_blank')]
    public ?string $amount = null;

    #[ApiProperty(
        description: 'Currency code. Read-only, derived from the account\'s currency, ignored on write.',
        writable: false,
        example: 'PLN',
    )]
    public ?string $currencyCode = null;

    #[ApiProperty(description: 'Free-text note. Optional.', example: 'Rent for September')]
    public ?string $description = null;

    #[ApiProperty(description: 'Whether the operation is confirmed. Defaults to false.', example: false)]
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
