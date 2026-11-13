<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A contractor was refused. The code is the translation key the web UI uses for the same problem, so the web form
 * and the API report it alike.
 */
final class ContractorException extends \RuntimeException implements BusinessError
{
    public const string NAME_NOT_UNIQUE = 'contractor.name_not_unique';

    private function __construct(string $message, private readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function nameNotUnique(): self
    {
        return new self('A contractor with this name already exists.', self::NAME_NOT_UNIQUE);
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
