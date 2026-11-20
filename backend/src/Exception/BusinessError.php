<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A refusal caused by the user's data or the current state, not a bug. The API returns it as a problem of its own
 * type (the code after "urn:finance-app:problem:"), with its title and parameters as extension members, so a client
 * can show its own message. The code is also the key of the web UI's translation of the same message, in that
 * message's domain ("validators" for form errors, "messages" otherwise). Kept free of HTTP: the processor that
 * catches it decides the status.
 */
interface BusinessError extends \Throwable
{
    public function getErrorCode(): string;

    /**
     * A short English summary of the problem type, the same for every occurrence (RFC 9457, section 3.1.3).
     */
    public function getErrorTitle(): string;

    /**
     * Public values for the message, e.g. ['fromCurrency' => 'PLN']: never anything secret. The names follow RFC 9457,
     * section 4 (a letter first, three characters or more), since they become members of the problem.
     *
     * @return array<string, string>
     */
    public function getErrorParams(): array;
}
