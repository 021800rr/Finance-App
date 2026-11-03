<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\ApiPath;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Safety net: API Platform maps this exception to a 4xx, and a 4xx "detail" is the exception message, which here is
 * SQL with table and constraint names. Known duplicates have their own codes; this covers any that was overlooked.
 */
final readonly class ApiUniqueViolationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Above 0: Symfony's ErrorListener logs the exception there, and API Platform answers below that.
        return [KernelEvents::EXCEPTION => ['onException', 10]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        $isApiPath = ApiPath::matches($path);
        $exception = $event->getThrowable();

        if ($isApiPath && $exception instanceof UniqueConstraintViolationException) {
            $event->setThrowable(new ConflictHttpException('This record already exists.', $exception));
        }
    }
}
