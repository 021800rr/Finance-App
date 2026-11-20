<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ApiResponseHeadersSubscriber;
use App\EventSubscriber\ConditionalGetSubscriber;
use Nelmio\CorsBundle\EventListener\CacheableResponseVaryListener;
use Nelmio\CorsBundle\EventListener\CorsListener;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiResponseHeadersSubscriberTest extends KernelTestCase
{
    /**
     * After the listeners whose headers it completes or replaces (CORS, the session's Cache-Control), before a
     * response becomes a 304, which keeps them.
     */
    public function testRunsAfterTheCorsAndSessionListenersAndBeforeTheConditionalGet(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $order = [];
        foreach ($dispatcher->getListeners(KernelEvents::RESPONSE) as $listener) {
            if (is_array($listener) && is_object($listener[0])) {
                $order[] = $listener[0]::class.'::'.$listener[1];
            }
        }

        self::assertSame(
            ['onResponse', -1010],
            ApiResponseHeadersSubscriber::getSubscribedEvents()[KernelEvents::RESPONSE] ?? null,
        );
        $headers = array_search(ApiResponseHeadersSubscriber::class.'::onResponse', $order, true);
        self::assertIsInt($headers);
        foreach ([
            CorsListener::class.'::onKernelResponse',
            CacheableResponseVaryListener::class.'::onResponse',
            SessionListener::class.'::onKernelResponse',
        ] as $before) {
            self::assertLessThan($headers, array_search($before, $order, true), $before);
        }
        self::assertGreaterThan($headers, array_search(ConditionalGetSubscriber::class.'::onResponse', $order, true));
    }
}
