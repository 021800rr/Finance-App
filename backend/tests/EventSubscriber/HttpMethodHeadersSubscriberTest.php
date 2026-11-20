<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\HttpMethodHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelEvents;

final class HttpMethodHeadersSubscriberTest extends TestCase
{
    /**
     * The handler is named by a string: a typo would only show as missing header fields.
     */
    public function testListensToResponsesWithAnExistingHandler(): void
    {
        $handler = HttpMethodHeadersSubscriber::getSubscribedEvents()[KernelEvents::RESPONSE] ?? null;

        self::assertIsString($handler);
        self::assertTrue(method_exists(HttpMethodHeadersSubscriber::class, $handler));
    }
}
