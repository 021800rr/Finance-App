<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Whether a request path belongs to the API, which config/routes/api_platform.yaml mounts under this prefix.
 * Only the prefix as a whole path segment counts: "/apis" or "/api-help" are not API paths.
 */
final class ApiPath
{
    public const string PREFIX = '/api';

    private function __construct()
    {
    }

    public static function matches(string $path): bool
    {
        return self::PREFIX === $path || str_starts_with($path, self::PREFIX.'/');
    }
}
