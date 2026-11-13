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
use App\ApiResource\Input\FixedExpenseInput;
use App\Entity\FixedExpense;
use App\State\FixedExpenseProcessor;
use App\State\FixedExpenseProvider;

/**
 * A fixed expense as the API returns it: every field is always there, the description only when there is one, and
 * the OpenAPI document says so (a generated client gets types without needless nulls). What a client sends is
 * FixedExpenseInput (input: on POST and PATCH), where any field may be missing until the validation says why.
 */
#[ApiResource(
    shortName: 'FixedExpense',
    description: 'A recurring monthly expense (always a negative amount, in PLN). Mirrors the "Fixed expenses" form.',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Lists all fixed expenses, ordered by day then by id descending (same order as the web list).',
            provider: FixedExpenseProvider::class,
        ),
        new Get(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Retrieves a single fixed expense by id.',
            provider: FixedExpenseProvider::class,
        ),
        new Post(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Creates a new fixed expense. Give contractorId, contractorName or both (of the same '
            .'contractor); a contractorName that matches no contractor creates one. '
            .'Amount must be negative and is always in PLN.',
            input: FixedExpenseInput::class,
            processor: FixedExpenseProcessor::class,
        ),
        new Patch(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Updates an existing fixed expense. Send the contractor as in POST, but only when it '
            .'changes: a contractorId or a contractorName that differs from the current contractor assigns the '
            .'fixed expense to that contractor (created when the name is new).',
            input: FixedExpenseInput::class,
            provider: FixedExpenseProvider::class,
            processor: FixedExpenseProcessor::class,
        ),
        new Delete(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Deletes a fixed expense.',
            provider: FixedExpenseProvider::class,
            processor: FixedExpenseProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final readonly class FixedExpenseResource
{
    private const string DESCRIPTION = 'Free-text note; left out when there is none.';

    public function __construct(
        #[ApiProperty(description: 'Fixed expense id.', required: true, example: 1)]
        public int $id,
        #[ApiProperty(description: 'Id of the contractor.', required: true, example: 10)]
        public int $contractorId,
        #[ApiProperty(description: 'Name of the contractor.', required: true, example: 'Example contractor')]
        public string $contractorName,
        #[ApiProperty(description: 'Day of the month the expense is due, 1-28.', required: true, example: 10)]
        public int $day,
        #[ApiProperty(
            description: 'Amount as a decimal string, always negative, in PLN.',
            required: true,
            example: '-200.00',
        )]
        public string $amount,
        #[ApiProperty(description: 'Currency code, always "PLN".', required: true, example: 'PLN')]
        public string $currencyCode,
        // Left out when absent, never sent as null: the schema says string, not string or null.
        #[ApiProperty(
            description: self::DESCRIPTION,
            example: 'Rent',
            schema: ['type' => 'string', 'description' => self::DESCRIPTION, 'example' => 'Rent'],
        )]
        public ?string $description,
    ) {
    }

    public static function fromEntity(FixedExpense $fixedExpense): self
    {
        return new self(
            id: $fixedExpense->getId(),
            contractorId: $fixedExpense->getContractor()->getId(),
            contractorName: $fixedExpense->getContractor()->getName(),
            day: $fixedExpense->getDay(),
            amount: $fixedExpense->getAmountAsMoney()->toDecimalString(),
            currencyCode: $fixedExpense->getCurrency()->getCode(),
            description: $fixedExpense->getDescription(),
        );
    }
}
