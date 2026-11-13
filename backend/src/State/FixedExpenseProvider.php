<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\FixedExpenseResource;
use App\ApiResource\Input\FixedExpenseInput;
use App\Exception\RecordNotFoundException;
use App\Repository\FixedExpenseRepository;

/**
 * @implements ProviderInterface<FixedExpenseResource|FixedExpenseInput>
 */
final readonly class FixedExpenseProvider implements ProviderInterface
{
    public function __construct(private FixedExpenseRepository $fixedExpenseRepository)
    {
    }

    /**
     * GET answers with FixedExpenseResource. PATCH and DELETE get the current values as FixedExpenseInput: PATCH lays
     * the request over them (what it leaves out stays), and the processor reads the record they belong to.
     *
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return FixedExpenseResource|FixedExpenseInput|list<FixedExpenseResource>
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): FixedExpenseResource|FixedExpenseInput|array {
        if (isset($uriVariables['id'])) {
            $fixedExpense = $this->fixedExpenseRepository->findByIdOrNull($uriVariables['id']);

            if (!$fixedExpense) {
                throw RecordNotFoundException::fixedExpense()->asHttp();
            }

            return $operation instanceof Get
                ? FixedExpenseResource::fromEntity($fixedExpense)
                : FixedExpenseInput::fromEntity($fixedExpense);
        }

        $fixedExpenses = $this->fixedExpenseRepository->findAllOrdered();

        return array_values(array_map(FixedExpenseResource::fromEntity(...), $fixedExpenses));
    }
}
