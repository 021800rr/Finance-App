<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AccountOperation;
use App\Entity\FixedExpense;
use App\Exception\FixedExpensePlanningException;
use App\Repository\AccountOperationRepository;
use App\Repository\AccountRepository;
use App\Repository\FixedExpenseRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FixedExpensePlannerService
{
    public function __construct(
        private AccountRepository $accountRepository,
        private FixedExpenseRepository $fixedExpenseRepository,
        private AccountOperationRepository $accountOperationRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     * @throws FixedExpensePlanningException
     */
    public function planNextMonth(): FixedExpensePlanResult
    {
        $account = $this->accountRepository->findMain();
        if (!$account) {
            throw FixedExpensePlanningException::noMainAccount();
        }

        if (!$account->getHousehold()->isOwnFunds()) {
            throw FixedExpensePlanningException::mainAccountNotOwn($account->getAccountNumber(), $account->getHousehold()->getName());
        }

        $targetMonth = new \DateTimeImmutable('first day of this month')->modify('+1 month');
        $year = (int) $targetMonth->format('Y');
        $month = (int) $targetMonth->format('m');

        $fixedExpenses = $this->fixedExpenseRepository->findAllOrdered();

        /** @var list<array{0: FixedExpense, 1: \DateTimeImmutable}> $operationsToCreate */
        $operationsToCreate = [];

        foreach ($fixedExpenses as $expense) {
            if ($expense->getCurrency()->getCode() !== $account->getCurrencyCode()) {
                throw FixedExpensePlanningException::currencyMismatch($expense->getCurrency()->getCode(), $account->getAccountNumber(), $account->getCurrencyCode());
            }

            $date = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $expense->getDay())
            );

            if (!$date) {
                continue;
            }

            if ($this->accountOperationRepository->existsForContractorAndDate(
                $account,
                $expense->getContractor(),
                $date,
                $expense->getAmount()
            )) {
                throw FixedExpensePlanningException::suspectedDuplicate($expense->getContractor()->getName(), $expense->getAmountAsMoney()->toDecimalString(), $date->format('Y-m-d'));
            }

            $operationsToCreate[] = [$expense, $date];
        }

        foreach ($operationsToCreate as [$expense, $date]) {
            $operation = new AccountOperation(
                $account,
                $expense->getContractor(),
                $date,
                $expense->getAmount(),
                $account->getCurrency(),
                description: $expense->getDescription(),
            );

            $this->accountOperationRepository->save($operation);
        }

        $this->entityManager->flush();

        return new FixedExpensePlanResult($targetMonth, count($operationsToCreate));
    }
}
