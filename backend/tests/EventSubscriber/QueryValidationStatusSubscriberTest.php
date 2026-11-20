<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use ApiPlatform\Symfony\EventListener\ExceptionListener;
use App\EventSubscriber\QueryValidationStatusSubscriber;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class QueryValidationStatusSubscriberTest extends KernelTestCase
{
    /**
     * The status must be set before API Platform turns the exception into the response.
     */
    public function testRunsBeforeApiPlatformAnswersTheException(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $order = [];
        foreach ($dispatcher->getListeners(KernelEvents::EXCEPTION) as $listener) {
            if (is_array($listener) && is_object($listener[0])) {
                $order[] = $listener[0]::class.'::'.$listener[1];
            }
        }

        self::assertSame(
            ['onException', -64],
            QueryValidationStatusSubscriber::getSubscribedEvents()[KernelEvents::EXCEPTION] ?? null,
        );
        $ours = array_search(QueryValidationStatusSubscriber::class.'::onException', $order, true);
        $apiPlatform = array_search(ExceptionListener::class.'::onKernelException', $order, true);
        self::assertIsInt($ours);
        self::assertIsInt($apiPlatform);
        self::assertLessThan($apiPlatform, $ours);
    }
}
