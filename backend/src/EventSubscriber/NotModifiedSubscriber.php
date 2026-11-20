<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Answers a GET or HEAD whose If-None-Match names the entity tag of the response, or is "*", with 304 Not Modified
 * and no content (RFC 9110, section 13.1.2). API Platform sets the entity tag (a hash of the content) but never
 * evaluates the condition. Only a successful response can become a 304: where the answer is an error, the condition
 * is ignored (section 13.2.1). Other methods: IfNoneMatchProvider.
 */
final readonly class NotModifiedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // After the listeners that add headers (API Platform's, CORS, the session's), so the 304 keeps them.
        return [KernelEvents::RESPONSE => ['onResponse', -1024]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if ($event->isMainRequest() && $response->isSuccessful() && null !== $response->getEtag()) {
            // Evaluates only GET and HEAD, with the weak comparison of section 8.8.3.2, and makes the response a 304.
            $response->isNotModified($event->getRequest());
        }
    }
}
