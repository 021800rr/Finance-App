<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Planning of the fixed expenses was refused. The exception message is the English text used by the API;
 * the translation key and parameters let the web UI show the same reason in the user's language.
 */
final class FixedExpensePlanningException extends \RuntimeException
{
    /**
     * @param array<string, string> $translationParameters
     */
    private function __construct(
        string $message,
        public readonly string $translationKey,
        public readonly array $translationParameters = [],
    ) {
        parent::__construct($message);
    }

    public static function noMainAccount(): self
    {
        return new self(
            'No account is marked as the main account. Mark one account as main first.',
            'fixed_expense.plan_no_main_account',
        );
    }

    public static function mainAccountNotOwn(string $accountNumber, string $householdName): self
    {
        return new self(
            sprintf(
                'Fixed expenses can only be planned on our own accounts, but the main account "%s" belongs to "%s". '
                .'Mark one of our accounts as main first.',
                $accountNumber,
                $householdName
            ),
            'fixed_expense.plan_main_not_own',
            [
                '%account%' => $accountNumber,
                '%household%' => $householdName,
            ],
        );
    }

    public static function currencyMismatch(string $expenseCurrency, string $accountNumber, string $accountCurrency): self
    {
        return new self(
            sprintf(
                'Fixed expenses are in %s, but the main account "%s" is in %s. Mark a %s account as main first.',
                $expenseCurrency,
                $accountNumber,
                $accountCurrency,
                $expenseCurrency
            ),
            'fixed_expense.plan_currency_mismatch',
            [
                '%expense_currency%' => $expenseCurrency,
                '%account%' => $accountNumber,
                '%account_currency%' => $accountCurrency],
        );
    }

    public static function suspectedDuplicate(string $contractorName, string $amount, string $date): self
    {
        return new self(
            sprintf(
                'Suspected duplicate: an operation for "%s" with amount %s on %s already exists. '
                .'Check the account before planning again.',
                $contractorName,
                $amount,
                $date
            ),
            'fixed_expense.plan_duplicate',
            [
                '%contractor%' => $contractorName,
                '%amount%' => $amount,
                '%date%' => $date,
            ],
        );
    }
}
