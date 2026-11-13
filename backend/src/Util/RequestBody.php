<?php

declare(strict_types=1);

namespace App\Util;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The fields of the JSON body exactly as the client sent them. Validators need this when the resource cannot tell:
 * on PATCH it already holds the stored values, and it holds null both for a field left out and for a sent null.
 */
final class RequestBody
{
    /**
     * @codeCoverageIgnore The class has only static members: no instance is ever made, so this never runs.
     */
    private function __construct()
    {
    }

    /**
     * @return array<mixed>|null null when there is no request or its body does not decode to a JSON object or list
     */
    public static function decode(RequestStack $requestStack): ?array
    {
        $body = json_decode((string) $requestStack->getCurrentRequest()?->getContent(), true);

        return is_array($body) ? $body : null;
    }
}
