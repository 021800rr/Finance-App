<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\ApiPath;
use App\Http\Preconditions;
use App\Http\ProblemResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Evaluates If-Match and If-None-Match (Preconditions) on the /api addresses outside API Platform: the login and the
 * token addresses, answered by the firewall. They have no representation, so If-Match always fails there (RFC 9110,
 * section 13.1.1) and the request gets 412 before anything is done. API Platform operations: PreconditionProvider.
 */
final readonly class PreconditionSubscriber implements EventSubscriberInterface
{
    public function __construct(private Preconditions $preconditions)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // After the router (32), which answers an unknown address or method first, and before the firewall (8),
        // which logs in or hands out tokens.
        return [KernelEvents::REQUEST => ['onRequest', 16]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (
            !$event->isMainRequest()
            || $request->isMethodCacheable()
            || !ApiPath::matches($request->getPathInfo())
            || $request->attributes->has('_api_resource_class')
        ) {
            return;
        }

        $refusal = $this->preconditions->refusal($request);
        if (null !== $refusal) {
            $event->setResponse(ProblemResponse::create(
                Response::HTTP_PRECONDITION_FAILED,
                ProblemResponse::httpErrorCode(Response::HTTP_PRECONDITION_FAILED),
                $refusal,
            ));
        }
    }
}
