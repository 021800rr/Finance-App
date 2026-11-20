<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\Preconditions;
use App\Http\ProblemResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Evaluates If-Match and If-None-Match on a GET or HEAD against the response itself (RFC 9110, section 13.2.2): a
 * failed If-Match gets 412, a matching If-None-Match a 304 Not Modified without content. API Platform sets the entity
 * tag (a hash of the content) but never evaluates the conditions. Only a successful response is evaluated: where the
 * answer is an error, the conditions are ignored (section 13.2.1). Other methods: PreconditionProvider and
 * PreconditionSubscriber.
 */
final readonly class ConditionalGetSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // After the listeners that add headers (API Platform's, CORS, the session's), so the 304 keeps them.
        return [KernelEvents::RESPONSE => ['onResponse', -1024]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        if (!$event->isMainRequest() || !$request->isMethodCacheable() || !$response->isSuccessful()) {
            return;
        }

        if (Preconditions::ifMatchFails($request, $response)) {
            $event->setResponse(ProblemResponse::create(
                Response::HTTP_PRECONDITION_FAILED,
                ProblemResponse::httpErrorCode(Response::HTTP_PRECONDITION_FAILED),
                'If-Match does not match the current representation.',
            ));
        } elseif (Preconditions::ifNoneMatchFails($request, $response)) {
            $response->setNotModified();
        }
    }
}
