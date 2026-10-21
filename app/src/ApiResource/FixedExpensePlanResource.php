<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Service\FixedExpensePlanResult;
use App\State\FixedExpensePlanProcessor;

#[ApiResource(
    shortName: 'FixedExpensePlan',
    description: 'Planning of the fixed expenses for the next month. Mirrors the "Plan next month" button '
    .'of the "Fixed expenses" page.',
    operations: [
        new Post(
            uriTemplate: '/fixed_expenses/plan{._format}',
            status: 201,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Creates one operation on the main account for every fixed expense, dated in the next '
            .'calendar month. Takes no request body. Responds with 409 when no account is marked as main, or '
            .'when a matching operation already exists (nothing is created then).',
            input: false,
            processor: FixedExpensePlanProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class FixedExpensePlanResource
{
    #[ApiProperty(description: 'Id of the main account the operations were created on.', writable: false, example: 1)]
    public ?int $accountId = null;

    #[ApiProperty(description: 'Planned month, format YYYY-MM.', writable: false, example: '2026-11')]
    public ?string $month = null;

    #[ApiProperty(description: 'Number of operations created.', writable: false, example: 5)]
    public ?int $createdCount = null;

    public static function fromResult(int $accountId, FixedExpensePlanResult $result): self
    {
        $resource = new self();
        $resource->accountId = $accountId;
        $resource->month = $result->month->format('Y-m');
        $resource->createdCount = $result->createdCount;

        return $resource;
    }
}
