<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A change of an account was refused. The code is also the translation key of the web UI's message.
 */
final class AccountException extends \RuntimeException implements BusinessError
{
    public const string MAIN_CHANGED_CONCURRENTLY = 'account.main_changed_concurrently';

    private function __construct(string $message, private readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function mainChangedConcurrently(): self
    {
        return new self(
            'Another request changed the main account at the same time. Check the main account and try again.',
            self::MAIN_CHANGED_CONCURRENTLY,
        );
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
