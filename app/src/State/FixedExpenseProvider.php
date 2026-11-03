<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\FixedExpenseResource;
use App\Exception\RecordNotFoundException;
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
     * @return FixedExpenseResource|list<FixedExpenseResource>
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): FixedExpenseResource|array {
        if (isset($uriVariables['id'])) {
            $fixedExpense = $this->fixedExpenseRepository->findByIdOrNull($uriVariables['id']);

            if (!$fixedExpense) {
                throw RecordNotFoundException::fixedExpense()->asHttp();
            }

            return FixedExpenseResource::fromEntity($fixedExpense);
        }

        $fixedExpenses = $this->fixedExpenseRepository->findAllOrdered();

        return array_values(array_map(FixedExpenseResource::fromEntity(...), $fixedExpenses));
    }
}
