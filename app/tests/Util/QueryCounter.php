<?php

declare(strict_types=1);

namespace App\Tests\Util;

use Psr\Log\AbstractLogger;
//use Stringable;

/**
 * Counts SQL statements executed on the default Doctrine DBAL connection, via Doctrine's own public
 * Doctrine\DBAL\Logging\Middleware (wired up as a "doctrine.middleware" service, test env only — see
 * config/packages/test/query_counter.yaml). Deliberately not implemented via reflection into
 * Symfony's @internal Symfony\Bridge\Doctrine\Middleware\Debug\Driver: that class can change without
 * notice between Symfony versions, whereas Doctrine\DBAL\Logging\Middleware is public, documented API.
 *
 * Tests inject this service, call reset() before the code under test, and assert on $count after.
 */
final class QueryCounter extends AbstractLogger
{
    public int $count = 0;

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        ++$this->count;
    }

    public function reset(): void
    {
        $this->count = 0;
    }
}
