<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\AcceptPatch;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Header fields that API Platform's answers lack for a method:
 *
 * - HEAD sends the header fields of GET (RFC 9110, section 9.3.2). API Platform adds Content-Location for every
 *   method but GET, HEAD included (HttpResponseHeadersTrait), so it goes.
 * - A PATCH refused for its Content-Type (415) says which ones a patch can have (RFC 5789, section 2.2).
 */
final readonly class HttpMethodHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(private AcceptPatch $acceptPatch)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if ($request->isMethod('HEAD') && $request->attributes->has('_api_resource_class')) {
            $response->headers->remove('Content-Location');
        }
        if ($request->isMethod('PATCH') && Response::HTTP_UNSUPPORTED_MEDIA_TYPE === $response->getStatusCode()) {
            $response->headers->set('Accept-Patch', $this->acceptPatch->value);
        }
    }
}
