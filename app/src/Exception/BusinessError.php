<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A refusal caused by the user's data or the current state, not a bug. The API returns its code and parameters
 * ("errorCode", "params"), so a client can show its own message. The code is also the key of the web UI's
 * translation of the same message, in that message's domain ("validators" for form errors, "messages" otherwise).
 * Kept free of HTTP: the processor that catches it decides the status.
 */
interface BusinessError extends \Throwable
{
    public function getErrorCode(): string;

    /**
     * Public values for the message, e.g. ['fromCurrency' => 'PLN']: never anything secret.
     *
     * @return array<string, string>
     */
    public function getErrorParams(): array;
}
