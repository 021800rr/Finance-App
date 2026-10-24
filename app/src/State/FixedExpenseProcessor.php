<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FixedExpenseResource;
use App\Constant\Currencies;
use App\Entity\FixedExpense;
use App\Repository\CurrencyRepository;
use App\Repository\FixedExpenseRepository;
use App\Service\ContractorResolver;
use App\ValueObject\Money;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<mixed, ?FixedExpenseResource>
 */
final readonly class FixedExpenseProcessor implements ProcessorInterface
{
    public function __construct(
        private FixedExpenseRepository $fixedExpenseRepository,
        private ContractorResolver $contractorResolver,
        private CurrencyRepository $currencyRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?FixedExpenseResource
    {
        if ($operation instanceof Delete) {
            $this->fixedExpenseRepository->remove($this->loadExisting($uriVariables), flush: true);

            return null;
        }

        if (!$data instanceof FixedExpenseResource) {
            throw new \InvalidArgumentException('Expected instance of FixedExpenseResource.');
        }

        $fixedExpense = $operation instanceof Patch ? $this->loadExisting($uriVariables) : null;

        $contractor = null === $fixedExpense
            ? $this->contractorResolver->resolve($data->contractorId, $data->contractorName)
            : $this->contractorResolver->resolveForUpdate($data->contractorId, $data->contractorName, $fixedExpense->getContractor());
        $day = $this->requireDay($data->day);
        $amountMinorUnits = $this->parseAmount($data->amount);
        $currency = $this->currencyRepository->findPlnOrThrow();

        try {
            if (null === $fixedExpense) {
                $fixedExpense = new FixedExpense($contractor, $day, $amountMinorUnits, $currency);
            } else {
                $fixedExpense->setContractor($contractor);
                $fixedExpense->setDay($day);
                $fixedExpense->setAmount($amountMinorUnits);
                $fixedExpense->setCurrency($currency);
            }
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        $fixedExpense->setDescription($data->description);

        $this->fixedExpenseRepository->save($fixedExpense, flush: true);

        return FixedExpenseResource::fromEntity($fixedExpense);
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function loadExisting(array $uriVariables): FixedExpense
    {
        $fixedExpense = $this->fixedExpenseRepository->findByIdOrNull($uriVariables['id'] ?? null);

        if (!$fixedExpense) {
            throw new NotFoundHttpException('Fixed expense not found.');
        }

        return $fixedExpense;
    }

    private function requireDay(?int $day): int
    {
        if (null === $day) {
            throw new UnprocessableEntityHttpException('Day is required.');
        }

        return $day;
    }

    private function parseAmount(?string $amount): int
    {
        $trimmed = trim((string) $amount);
        if ('' === $trimmed) {
            throw new UnprocessableEntityHttpException('Amount is required.');
        }

        try {
            return Money::fromDecimalString($trimmed, Currencies::SETTLEMENT_CURRENCY_CODE)->getMinorUnits();
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
    }
}
