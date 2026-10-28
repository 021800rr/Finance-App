<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FixedExpenseResource;
use App\Constant\Currencies;
use App\Entity\Currency;
use App\Entity\FixedExpense;
use App\Repository\CurrencyRepository;
use App\Repository\FixedExpenseRepository;
use App\Service\ContractorResolver;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ?FixedExpenseResource {
        if ($operation instanceof Delete) {
            $this->fixedExpenseRepository->remove($this->loadExisting($uriVariables), flush: true);

            return null;
        }

        if (!$data instanceof FixedExpenseResource) {
            throw new \InvalidArgumentException('Expected instance of FixedExpenseResource.');
        }

        $existingFixedExpense = $operation instanceof Patch ? $this->loadExisting($uriVariables) : null;

        $day = $this->requireDay($data->day);
        $amountMinorUnits = $this->parseAmount($data->amount);
        $currency = $this->currencyRepository->findPlnOrThrow();

        try {
            // Include the resolver's contractor flush and the final expense flush in one transaction.
            /** @var FixedExpense $fixedExpense */
            $fixedExpense = $this->entityManager->wrapInTransaction(function () use (
                $existingFixedExpense,
                $data,
                $day,
                $amountMinorUnits,
                $currency,
            ): FixedExpense {
                $fixedExpense = null === $existingFixedExpense
                    ? $this->create($data, $day, $amountMinorUnits, $currency)
                    : $this->update($existingFixedExpense, $data, $day, $amountMinorUnits, $currency);

                $this->fixedExpenseRepository->save($fixedExpense);

                return $fixedExpense;
            });
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return FixedExpenseResource::fromEntity($fixedExpense);
    }

    private function create(
        FixedExpenseResource $data,
        int $day,
        int $amountMinorUnits,
        Currency $currency,
    ): FixedExpense {
        $fixedExpense = new FixedExpense(
            $this->contractorResolver->resolve($data->contractorId, $data->contractorName),
            $day,
            $amountMinorUnits,
            $currency,
        );
        $fixedExpense->setDescription($data->description);

        return $fixedExpense;
    }

    private function update(
        FixedExpense $fixedExpense,
        FixedExpenseResource $data,
        int $day,
        int $amountMinorUnits,
        Currency $currency,
    ): FixedExpense {
        $contractor = $this->contractorResolver->resolveForUpdate(
            $data->contractorId,
            $data->contractorName,
            $fixedExpense->getContractor(),
        );

        $fixedExpense->setContractor($contractor);
        $fixedExpense->setDay($day);
        $fixedExpense->setAmount($amountMinorUnits);
        $fixedExpense->setCurrency($currency);
        $fixedExpense->setDescription($data->description);

        return $fixedExpense;
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
