<?php

declare(strict_types=1);

namespace App\Tests\Util;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;

/**
 * Adapts QueryCounter (a plain PSR logger) into a "doctrine.middleware" tagged service, by delegating
 * to Doctrine DBAL's own public Doctrine\DBAL\Logging\Middleware — see QueryCounter's docblock for why.
 */
final readonly class QueryCounterMiddleware implements MiddlewareInterface
{
    private LoggingMiddleware $delegate;

    public function __construct(QueryCounter $logger)
    {
        $this->delegate = new LoggingMiddleware($logger);
    }

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return $this->delegate->wrap($driver);
    }
}
