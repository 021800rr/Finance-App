<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\FixedExpenseResource;
use App\Repository\FixedExpenseRepository;

/**
 * @implements ProviderInterface<FixedExpenseResource>
 */
final readonly class FixedExpenseProvider implements ProviderInterface
{
    public function __construct(private FixedExpenseRepository $fixedExpenseRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return FixedExpenseResource|list<FixedExpenseResource>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FixedExpenseResource|array|null
    {
        if (isset($uriVariables['id'])) {
            $fixedExpense = $this->fixedExpenseRepository->find($uriVariables['id']);

            return $fixedExpense ? FixedExpenseResource::fromEntity($fixedExpense) : null;
        }

        $fixedExpenses = $this->fixedExpenseRepository->findAllOrdered();

        return array_values(array_map(FixedExpenseResource::fromEntity(...), $fixedExpenses));
    }
}
