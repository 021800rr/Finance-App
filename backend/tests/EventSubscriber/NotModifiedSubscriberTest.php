<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\NotModifiedSubscriber;
use Nelmio\CorsBundle\EventListener\CacheableResponseVaryListener;
use Nelmio\CorsBundle\EventListener\CorsListener;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

final class NotModifiedSubscriberTest extends KernelTestCase
{
    /**
     * A 304 carries the headers the 200 would have had (RFC 9110, section 15.4.5): Vary, Cache-Control and the CORS
     * ones. The listeners that add them must run before the response becomes a 304.
     */
    public function testRunsAfterTheListenersThatAddHeaders(): void
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
            ['onResponse', -1024],
            NotModifiedSubscriber::getSubscribedEvents()[KernelEvents::RESPONSE] ?? null,
        );
        $notModified = array_search(NotModifiedSubscriber::class.'::onResponse', $order, true);
        self::assertIsInt($notModified);
        foreach ([
            CorsListener::class.'::onKernelResponse',
            CacheableResponseVaryListener::class.'::onResponse',
            SessionListener::class.'::onKernelResponse',
        ] as $addsHeaders) {
            $position = array_search($addsHeaders, $order, true);
            self::assertIsInt($position, $addsHeaders);
            self::assertLessThan($notModified, $position, $addsHeaders);
        }
    }
}
