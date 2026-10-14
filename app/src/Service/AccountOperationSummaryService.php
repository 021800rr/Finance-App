<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Account;
use App\Repository\AccountOperationRepository;
use App\ValueObject\Money;

final readonly class AccountOperationSummaryService
{
    public function __construct(private AccountOperationRepository $accountOperationRepository)
    {
    }

    /**
     * Account balances with and without operations dated in the future. "showSaldoToday" is true only when the two differ.
     *
     * @return array{saldoAll: Money, saldoToday: Money, showSaldoToday: bool}
     */
    public function getBalances(Account $account): array
    {
        $sums = $this->accountOperationRepository->sumBalancesForAccount($account, new \DateTimeImmutable('today'));

        $currencyCode = $account->getCurrencyCode();
        $saldoAll = Money::fromMinorUnits($sums['total'], $currencyCode);
        $saldoToday = Money::fromMinorUnits($sums['upToToday'], $currencyCode);

        return [
            'saldoAll' => $saldoAll,
            'saldoToday' => $saldoToday,
            'showSaldoToday' => !$saldoAll->equals($saldoToday),
        ];
    }
}
