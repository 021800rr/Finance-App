<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Account;
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
        return $this->entityManager->wrapInTransaction(fn (): FixedExpensePlanResult => $this->plan());
    }

    /**
     * @throws \DateMalformedStringException
     * @throws FixedExpensePlanningException
     */
    private function plan(): FixedExpensePlanResult
    {
        // A concurrent planning waits here until this one commits, so its duplicate check sees these operations.
        $account = $this->accountRepository->findMainForUpdate();
        $this->validateAccount($account);

        $targetMonth = new \DateTimeImmutable('first day of next month');
        $year = (int) $targetMonth->format('Y');
        $month = (int) $targetMonth->format('m');

        $fixedExpenses = $this->fixedExpenseRepository->findAllOrdered();

        $operationsToCreate = $this->planFixedExpenses($fixedExpenses, $account, $year, $month);

        $this->persistAccountOperations($operationsToCreate, $account);

        return new FixedExpensePlanResult($targetMonth, count($operationsToCreate));
    }

    /**
     * @phpstan-assert Account $account
     *
     * @throws FixedExpensePlanningException
     */
    private function validateAccount(?Account $account): void
    {
        if (!$account) {
            throw FixedExpensePlanningException::noMainAccount();
        }

        $household = $account->getHousehold();
        if (!$household->isOwnFunds()) {
            throw FixedExpensePlanningException::mainAccountNotOwn($account->getAccountNumber(), $household->getName());
        }

        // Same rule as for operations and transfers: a hidden account takes no new operations.
        if ($account->isHidden()) {
            throw FixedExpensePlanningException::mainAccountHidden($account->getAccountNumber());
        }
    }

    /**
     * Checks every expense before anything is saved, so a single refusal leaves the whole month unplanned.
     *
     * @param FixedExpense[] $fixedExpenses
     *
     * @return list<array{FixedExpense, \DateTimeImmutable}> each expense with the date of its operation
     *
     * @throws FixedExpensePlanningException
     */
    private function planFixedExpenses(array $fixedExpenses, Account $account, int $year, int $month): array
    {
        $accountCurrency = $account->getCurrencyCode();
        $operationsToCreate = [];

        foreach ($fixedExpenses as $expense) {
            $currency = $expense->getCurrency()->getCode();
            if ($currency !== $accountCurrency) {
                $accountNumber = $account->getAccountNumber();

                throw FixedExpensePlanningException::currencyMismatch($currency, $accountNumber, $accountCurrency);
            }

            $date = new \DateTimeImmutable()->setDate($year, $month, $expense->getDay())->setTime(0, 0);

            $contractor = $expense->getContractor();
            if ($this->accountOperationRepository->existsForContractorAndDate(
                $account,
                $contractor,
                $date,
                $expense->getAmount()
            )) {
                $amount = $expense->getAmountAsMoney()->toDecimalString();
                $day = $date->format('Y-m-d');

                throw FixedExpensePlanningException::suspectedDuplicate($contractor->getName(), $amount, $day);
            }

            $operationsToCreate[] = [$expense, $date];
        }

        return $operationsToCreate;
    }

    /**
     * @param list<array{FixedExpense, \DateTimeImmutable}> $operationsToCreate each expense with its operation date
     */
    private function persistAccountOperations(array $operationsToCreate, Account $account): void
    {
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
    }
}
