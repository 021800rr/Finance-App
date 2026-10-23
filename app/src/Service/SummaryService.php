<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\Currencies;
use App\Repository\AccountBalanceReportRepository;
use App\Repository\CurrencyExchangeRateRepository;
use App\Repository\GoldPriceRepository;
use App\Repository\HouseholdRepository;
use App\ValueObject\Money;
use Doctrine\DBAL\Exception;

/**
 * @phpstan-type AccountRow array{
 *     id: int,
 *     bank: string,
 *     type: string,
 *     currency: string,
 *     account_number: string,
 *     hidden: bool,
 *     household: string,
 *     is_own_funds: bool,
 *     is_retirement: bool,
 *     saldo: numeric-string
 * }
 * @phpstan-type EnrichedAccountRow array{
 *     id: int,
 *     bank: string,
 *     type: string,
 *     currency: string,
 *     account_number: string,
 *     hidden: bool,
 *     household: string,
 *     is_own_funds: bool,
 *     is_retirement: bool,
 *     saldo: numeric-string,
 *     saldoMoney: Money,
 *     plnValue: ?Money
 * }
 * @phpstan-type PublicAccountRow array{
 *     id: int,
 *     bank: string,
 *     type: string,
 *     currency: string,
 *     account_number: string,
 *     hidden: bool,
 *     household: string,
 *     is_own_funds: bool,
 *     is_retirement: bool,
 *     saldo: Money,
 *     missing_rate: bool
 * }
 */
final readonly class SummaryService
{
    public function __construct(
        private AccountBalanceReportRepository $accountBalanceReportRepository,
        private CurrencyExchangeRateRepository $currencyExchangeRateRepository,
        private GoldPriceRepository $goldPriceRepository,
        private HouseholdRepository $householdRepository,
    ) {
    }

    /**
     * @return array{
     *     accounts: list<PublicAccountRow>,
     *     byType: array<string, array{raw: array<string, Money>, pln: Money}>,
     *     byHousehold: array<string, Money>,
     *     ownFundsHouseholdName: string,
     *     gold: Money,
     *     retirementBalance: Money,
     *     retirementBalanceWithGold: Money,
     *     ratesByBank: array<string, array<string, Money>>,
     *     accountsMissingRate: list<string>
     * }
     *
     * @throws Exception
     */
    public function getSummary(bool $showHidden = false): array
    {
        $accounts = $this->fetchAccounts($showHidden);
        $ratesByBank = $this->currencyExchangeRateRepository->fetchLatestRatesToPlnByBank();
        $goldValue = Money::fromMinorUnits(
            minorUnits: $this->goldPriceRepository->fetchLatestTotalPricePln(),
            currencyCode: Currencies::SETTLEMENT_CURRENCY_CODE
        );

        $enrichedAccounts = $this->enrichAccountsWithPlnValue($accounts, $ratesByBank);
        // Per-type and retirement totals describe our own money only; the mother's accounts show up
        // solely in the per-household totals.
        $ownFundsAccounts = array_values(array_filter(
            $enrichedAccounts,
            static fn (array $account) => $account['is_own_funds']
        ));
        $retirementBalance = $this->sumRetirementBalance($ownFundsAccounts);
        $ownFundsHouseholdName = $this->householdRepository->findOwnFundsHouseholdName();
        $byHousehold = $this->summarizeByHousehold($enrichedAccounts, $goldValue, $ownFundsHouseholdName);

        return [
            'accounts' => $this->toPublicAccountRows($enrichedAccounts),
            'byType' => $this->summarizeByType($ownFundsAccounts),
            'byHousehold' => $byHousehold,
            'ownFundsHouseholdName' => $ownFundsHouseholdName,
            'gold' => $goldValue,
            'retirementBalance' => $retirementBalance,
            'retirementBalanceWithGold' => $retirementBalance->add($goldValue),
            'ratesByBank' => array_map(
                static fn (array $rates) => array_map(
                    static fn (int $rate) => Money::fromMinorUnits(
                        $rate, Currencies::SETTLEMENT_CURRENCY_CODE, Currencies::EXCHANGE_RATE_SCALE
                    ),
                    $rates
                ),
                $ratesByBank
            ),
            'accountsMissingRate' => $this->listMissingRateAccounts($enrichedAccounts),
        ];
    }

    /**
     * @return list<AccountRow>
     *
     * @throws Exception
     */
    private function fetchAccounts(bool $showHidden): array
    {
        $allAccounts = $this->accountBalanceReportRepository->fetchAccountBalances();

        return $showHidden
            ? $allAccounts
            : array_values(array_filter($allAccounts, static fn (array $account) => !$account['hidden']));
    }

    /**
     * @param list<AccountRow>                  $accounts
     * @param array<string, array<string, int>> $ratesByBank
     *
     * @return list<EnrichedAccountRow>
     */
    private function enrichAccountsWithPlnValue(array $accounts, array $ratesByBank): array
    {
        return array_map(
            function (array $account) use ($ratesByBank) {
                $saldoMoney = Money::fromMinorUnits((int) $account['saldo'], $account['currency']);

                return [
                    ...$account,
                    'saldoMoney' => $saldoMoney,
                    'plnValue' => $this->convertToPln($saldoMoney, $account['currency'], $account['bank'], $ratesByBank),
                ];
            },
            $accounts
        );
    }

    /**
     * @param list<EnrichedAccountRow> $enrichedAccounts
     */
    private function sumRetirementBalance(array $enrichedAccounts): Money
    {
        return array_reduce(
            array_filter($enrichedAccounts, static fn (array $a) => $a['is_retirement'] && null !== $a['plnValue']),
            static fn (Money $total, array $a) => $total->add($a['plnValue']),
            Money::zero(Currencies::SETTLEMENT_CURRENCY_CODE)
        );
    }

    /**
     * @param list<EnrichedAccountRow> $enrichedAccounts
     *
     * @return array<string, Money>
     */
    private function summarizeByHousehold(array $enrichedAccounts, Money $goldValue, string $ownFundsHouseholdName): array
    {
        $byHousehold = [];

        foreach ($enrichedAccounts as $account) {
            if (null === $account['plnValue']) {
                continue;
            }

            $household = $account['household'];
            $byHousehold[$household] = ($byHousehold[$household] ?? Money::zero(Currencies::SETTLEMENT_CURRENCY_CODE))
                ->add($account['plnValue']);
        }

        $byHousehold[$ownFundsHouseholdName] =
            ($byHousehold[$ownFundsHouseholdName] ?? Money::zero(Currencies::SETTLEMENT_CURRENCY_CODE))
                ->add($goldValue);

        return $byHousehold;
    }

    /**
     * @param list<EnrichedAccountRow> $enrichedAccounts
     *
     * @return list<PublicAccountRow>
     */
    private function toPublicAccountRows(array $enrichedAccounts): array
    {
        return array_map(
            static function (array $account) {
                $missingRate = null === $account['plnValue'];
                $saldoMoney = $account['saldoMoney'];
                unset($account['saldoMoney'], $account['plnValue']);

                return [...$account, 'saldo' => $saldoMoney, 'missing_rate' => $missingRate];
            },
            $enrichedAccounts
        );
    }

    /**
     * @param list<EnrichedAccountRow> $enrichedAccounts
     *
     * @return array<string, array{raw: array<string, Money>, pln: Money}>
     */
    private function summarizeByType(array $enrichedAccounts): array
    {
        $byType = [];

        foreach ($enrichedAccounts as $account) {
            $type = $account['type'];
            $currency = $account['currency'];

            $byType[$type]['raw'][$currency] =
                ($byType[$type]['raw'][$currency] ?? Money::zero($currency))->add($account['saldoMoney']);

            if (null !== $account['plnValue']) {
                $byType[$type]['pln'] =
                    ($byType[$type]['pln'] ?? Money::zero(Currencies::SETTLEMENT_CURRENCY_CODE))->add($account['plnValue']);
            }
        }

        return array_map(
            static fn (array $entry) => [
                'raw' => $entry['raw'],
                'pln' => $entry['pln'] ?? Money::zero(Currencies::SETTLEMENT_CURRENCY_CODE),
            ],
            $byType
        );
    }

    /**
     * @param list<EnrichedAccountRow> $enrichedAccounts
     *
     * @return list<string>
     */
    private function listMissingRateAccounts(array $enrichedAccounts): array
    {
        $missing = array_filter($enrichedAccounts, static fn (array $a) => null === $a['plnValue']);

        return array_values(array_map(
            static fn (array $a) => "{$a['bank']} {$a['account_number']}",
            $missing
        ));
    }

    /**
     * @param array<string, array<string, int>> $ratesByBank
     */
    private function convertToPln(Money $saldoMoney, string $currencyCode, string $bank, array $ratesByBank): ?Money
    {
        if (Currencies::SETTLEMENT_CURRENCY_CODE === $currencyCode) {
            return $saldoMoney;
        }

        $rateRaw = $ratesByBank[$bank][$currencyCode] ?? null;
        if (null === $rateRaw) {
            return null;
        }

        $rateDecimal = Money::fromMinorUnits(
            (int) $rateRaw,
            Currencies::SETTLEMENT_CURRENCY_CODE,
            Currencies::EXCHANGE_RATE_SCALE
        )->toDecimalString();

        return $saldoMoney->convertToPln($rateDecimal);
    }
}
