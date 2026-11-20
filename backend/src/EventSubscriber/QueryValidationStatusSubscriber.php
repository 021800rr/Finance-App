<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A GET or HEAD has no content, so a validation error there is about the address, its query parameters: 400, not
 * 422, which is for content that is well-formed but cannot be processed (RFC 9110, sections 15.5.1 and 15.5.21).
 * API Platform answers every ValidationException with 422.
 */
final readonly class QueryValidationStatusSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Before API Platform's error listener (-96) turns the exception into the response.
        return [KernelEvents::EXCEPTION => ['onException', -64]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof ValidationException && $event->getRequest()->isMethodCacheable()) {
            $exception->setStatus(Response::HTTP_BAD_REQUEST);
        }
    }
}
