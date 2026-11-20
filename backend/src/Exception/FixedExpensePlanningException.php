<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Planning of the fixed expenses was refused. The exception message is the English text used by the API;
 * the translation key and parameters let the web UI show the same reason in the user's language.
 * The API also returns the key as the problem type and its own parameters, listed explicitly per refusal so that
 * rewording a translation can never change the API contract.
 */
final class FixedExpensePlanningException extends \RuntimeException implements BusinessError
{
    /**
     * @param array<string, string> $translationParameters
     * @param array<string, string> $errorParams
     */
    private function __construct(
        string $message,
        public readonly string $translationKey,
        private readonly string $errorTitle,
        public readonly array $translationParameters = [],
        private readonly array $errorParams = [],
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->translationKey;
    }

    public function getErrorTitle(): string
    {
        return $this->errorTitle;
    }

    public function getErrorParams(): array
    {
        return $this->errorParams;
    }

    public static function noMainAccount(): self
    {
        return new self(
            'No account is marked as the main account. Mark one account as main first.',
            'fixed_expense.plan_no_main_account',
            'No main account',
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
            'Main account not ours',
            [
                '%account%' => $accountNumber,
                '%household%' => $householdName,
            ],
            ['account' => $accountNumber, 'household' => $householdName],
        );
    }

    public static function mainAccountHidden(string $accountNumber): self
    {
        return new self(
            sprintf(
                'The main account "%s" is hidden, so no new operations can be added to it. '
                .'Show the account again or mark another account as main first.',
                $accountNumber
            ),
            'fixed_expense.plan_main_hidden',
            'Main account hidden',
            ['%account%' => $accountNumber],
            ['account' => $accountNumber],
        );
    }

    public static function currencyMismatch(
        string $expenseCurrency,
        string $accountNumber,
        string $accountCurrency,
    ): self {
        return new self(
            sprintf(
                'Fixed expenses are in %s, but the main account "%s" is in %s. Mark a %s account as main first.',
                $expenseCurrency,
                $accountNumber,
                $accountCurrency,
                $expenseCurrency
            ),
            'fixed_expense.plan_currency_mismatch',
            'Currency of the main account differs',
            [
                '%expense_currency%' => $expenseCurrency,
                '%account%' => $accountNumber,
                '%account_currency%' => $accountCurrency,
            ],
            [
                'expenseCurrency' => $expenseCurrency,
                'account' => $accountNumber,
                'accountCurrency' => $accountCurrency,
            ],
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
            'Suspected duplicate',
            [
                '%contractor%' => $contractorName,
                '%amount%' => $amount,
                '%date%' => $date,
            ],
            ['contractor' => $contractorName, 'amount' => $amount, 'date' => $date],
        );
    }
}
