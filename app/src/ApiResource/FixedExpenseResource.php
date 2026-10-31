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
use App\Entity\FixedExpense;
use App\State\FixedExpenseProcessor;
use App\State\FixedExpenseProvider;
use App\Validator\ContractorGiven;
use App\Validator\NoNullCharacter;
use Symfony\Component\Validator\Constraints as Assert;

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
            description: 'Creates a new fixed expense. Either contractorId or contractorName must be given; '
            .'if contractorName does not match an existing contractor, one is created automatically. '
            .'Amount must be negative and is always in PLN.',
            processor: FixedExpenseProcessor::class,
        ),
        new Patch(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Updates an existing fixed expense. Send the contractor as in POST, but only when it changes: a '
            .'contractorId or a contractorName that differs from the current contractor assigns the fixed expense to '
            .'that contractor (created when the name is new).',
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
final class FixedExpenseResource
{
    #[ApiProperty(description: 'Fixed expense id. Read-only, ignored on write.', writable: false, example: 1)]
    public ?int $id = null;

    #[ApiProperty(
        description: 'Id of an existing contractor. Provide this OR contractorName, not both '
        .'(leave this null when using contractorName).',
        example: null,
    )]
    public ?int $contractorId = null;

    #[ApiProperty(
        description: 'Contractor name. Used to find an existing contractor by name, or create a new one '
        .'if none matches. Ignored if contractorId is set.',
        example: 'Rent',
    )]
    #[Assert\Length(max: 255, maxMessage: 'contractor.name_max_length')]
    #[NoNullCharacter]
    #[ContractorGiven]
    public ?string $contractorName = null;

    #[ApiProperty(description: 'Day of the month the expense is due, 1-28. Required.', example: 10)]
    #[Assert\NotNull(message: 'fixed_expense.day_required')]
    #[Assert\Range(notInRangeMessage: 'fixed_expense.day_out_of_range', min: 1, max: 28)]
    public ?int $day = null;

    #[ApiProperty(
        description: 'Amount as a decimal string. Must be negative, e.g. "-200.00". Always in PLN. Required.',
        example: '-200.00',
    )]
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'fixed_expense.amount_not_blank', normalizer: 'trim'),
        new Assert\Range(
            notInRangeMessage: 'fixed_expense.amount_out_of_range',
            invalidMessage: 'common.numeric_invalid',
            min: -999999.99,
            max: -0.01,
        ),
    ])]
    public ?string $amount = null;

    #[ApiProperty(
        description: 'Currency code. Read-only, always "PLN", ignored on write.',
        writable: false,
        example: 'PLN',
    )]
    public ?string $currencyCode = null;

    #[ApiProperty(
        description: 'Free-text note. Optional, at most 255 characters after trimming. Surrounding spaces are removed; '
        .'a blank note is stored as null.',
        example: 'Rent',
    )]
    #[Assert\Length(max: 255, normalizer: 'trim', maxMessage: 'common.description_max_length')]
    #[NoNullCharacter]
    public ?string $description = null;

    public static function fromEntity(FixedExpense $fixedExpense): self
    {
        $resource = new self();
        $resource->id = $fixedExpense->getId();
        $resource->contractorId = $fixedExpense->getContractor()->getId();
        $resource->contractorName = $fixedExpense->getContractor()->getName();
        $resource->day = $fixedExpense->getDay();
        $resource->amount = $fixedExpense->getAmountAsMoney()->toDecimalString();
        $resource->currencyCode = $fixedExpense->getCurrency()->getCode();
        $resource->description = $fixedExpense->getDescription();

        return $resource;
    }
}
