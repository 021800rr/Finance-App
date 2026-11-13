<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A transfer was refused because of its input. The codes are the translation keys the transfer form uses for the
 * same problems, so the web form and the API report them alike. Still an InvalidArgumentException, so existing
 * callers that catch that keep working.
 */
final class TransferException extends \InvalidArgumentException implements BusinessError
{
    public const string ACCOUNTS_MUST_DIFFER = 'transfer.accounts_must_differ';
    public const string AMOUNT_TO_REQUIRED = 'transfer.amount_to_required_for_currency_exchange';

    /**
     * @param array<string, string> $errorParams
     */
    private function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly array $errorParams,
    ) {
        parent::__construct($message);
    }

    public static function accountsMustDiffer(): self
    {
        return new self('Cannot transfer between the same account.', self::ACCOUNTS_MUST_DIFFER, []);
    }

    public static function amountToRequired(string $fromCurrency, string $toCurrency): self
    {
        return new self(
            'For different currencies amountTo is required.',
            self::AMOUNT_TO_REQUIRED,
            ['fromCurrency' => $fromCurrency, 'toCurrency' => $toCurrency],
        );
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorParams(): array
    {
        return $this->errorParams;
    }
}
