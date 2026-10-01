<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Repository\AccountOperationRepository;
use App\ValueObject\Money;

final readonly class AccountOperationSummaryService
{
    public function __construct(private AccountOperationRepository $accountOperationRepository)
    {
    }

    /**
     * @return array{
     *     operations: AccountOperation[],
     *     saldoAll: Money,
     *     saldoToday: Money,
     *     showSaldoToday: bool
     * }
     */
    public function getForAccount(Account $account): array
    {
        $operations = $this->accountOperationRepository->findByAccountOrdered($account);

        $accountCurrencyCode = $account->getCurrency()->getCode();
        $saldoAll = Money::zero($accountCurrencyCode);
        $saldoToday = Money::zero($accountCurrencyCode);
        $today = new \DateTimeImmutable('today');

        foreach ($operations as $op) {
            $operationCurrencyCode = $op->getCurrency()->getCode();
            if ($operationCurrencyCode !== $accountCurrencyCode) {
                throw new \RuntimeException("Operation #{$op->getId()} currency ({$operationCurrencyCode}) does not match account currency ({$accountCurrencyCode}).");
            }

            $operationMoney = Money::fromMinorUnits($op->getAmount(), $operationCurrencyCode);
            $saldoAll = $saldoAll->add($operationMoney);

            if ($op->getOperationDate() <= $today) {
                $saldoToday = $saldoToday->add($operationMoney);
            }
        }

        return [
            'operations' => $operations,
            'saldoAll' => $saldoAll,
            'saldoToday' => $saldoToday,
            'showSaldoToday' => !$saldoAll->equals($saldoToday),
        ];
    }
}
