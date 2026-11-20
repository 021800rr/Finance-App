<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\PreconditionSubscriber;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\EventListener\FirewallListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\KernelEvents;

final class PreconditionSubscriberTest extends KernelTestCase
{
    /**
     * After the router, which answers an unknown address or method first, and before the firewall, which would log
     * in or hand out tokens before the preconditions are evaluated.
     */
    public function testRunsBetweenTheRouterAndTheFirewall(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $order = [];
        foreach ($dispatcher->getListeners(KernelEvents::REQUEST) as $listener) {
            if (is_array($listener) && is_object($listener[0])) {
                $order[] = $listener[0]::class.'::'.$listener[1];
            }
        }

        self::assertSame(
            ['onRequest', 16],
            PreconditionSubscriber::getSubscribedEvents()[KernelEvents::REQUEST] ?? null,
        );
        $preconditions = array_search(PreconditionSubscriber::class.'::onRequest', $order, true);
        $router = array_search(RouterListener::class.'::onKernelRequest', $order, true);
        // In debug mode the firewall listener is wrapped in a traceable subclass.
        $firewalls = array_keys(array_filter(
            $order,
            static fn (string $listener): bool => str_ends_with($listener, '::onKernelRequest')
                && is_a(explode('::', $listener)[0], FirewallListener::class, true),
        ));
        $firewall = $firewalls[0] ?? null;
        self::assertIsInt($preconditions);
        self::assertIsInt($router);
        self::assertIsInt($firewall);
        self::assertLessThan($preconditions, $router);
        self::assertGreaterThan($preconditions, $firewall);
    }
}
