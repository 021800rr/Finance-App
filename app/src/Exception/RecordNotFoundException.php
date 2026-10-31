<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * The record named in the address does not exist. Unlike an unknown address ("http.not_found"), a client can expect
 * this at any time, e.g. after someone deleted the record, so each kind of record has its own code.
 */
final class RecordNotFoundException extends \RuntimeException implements BusinessError
{
    private function __construct(string $message, private readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function account(): self
    {
        return new self('Account not found.', 'account.not_found');
    }

    public static function accountOperation(): self
    {
        return new self('Account operation not found.', 'account_operation.not_found');
    }

    public static function accountType(): self
    {
        return new self('Account type not found.', 'account_type.not_found');
    }

    public static function bank(): self
    {
        return new self('Bank not found.', 'bank.not_found');
    }

    public static function contractor(): self
    {
        return new self('Contractor not found.', 'contractor.not_found');
    }

    public static function currency(): self
    {
        return new self('Currency not found.', 'currency.not_found');
    }

    public static function currencyExchangeRate(): self
    {
        return new self('Exchange rate not found.', 'currency_exchange_rate.not_found');
    }

    public static function fixedExpense(): self
    {
        return new self('Fixed expense not found.', 'fixed_expense.not_found');
    }

    public static function goldAsset(): self
    {
        return new self('Gold asset not found.', 'gold_asset.not_found');
    }

    public static function goldForm(): self
    {
        return new self('Gold form not found.', 'gold_form.not_found');
    }

    public static function goldPrice(): self
    {
        return new self('Gold price entry not found.', 'gold_price.not_found');
    }

    public static function household(): self
    {
        return new self('Household not found.', 'household.not_found');
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorParams(): array
    {
        return [];
    }
}
