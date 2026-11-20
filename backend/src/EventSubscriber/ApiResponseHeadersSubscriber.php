<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\ApiPath;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Header fields every /api response gets, whoever built it (API Platform, ProblemResponse, the bundles):
 *
 * - X-Content-Type-Options: nosniff and X-Frame-Options: DENY. API Platform adds them only to its own responses;
 *   Spring Security adds both to every response by default.
 * - Vary: Origin wherever Access-Control-Allow-Origin names the origin of the request, errors too: a cache must not
 *   give the answer for one origin to another (Fetch Standard, "CORS protocol and HTTP caches").
 * - Cache-Control: no-store and Pragma: no-cache on the responses that carry tokens (the login and the refresh), as
 *   RFC 6749, section 5.1 asks of an OAuth token endpoint: nothing may store them.
 */
final readonly class ApiResponseHeadersSubscriber implements EventSubscriberInterface
{
    private const array TOKEN_ADDRESSES = ['/api/login_check', '/api/token/refresh'];

    public static function getSubscribedEvents(): array
    {
        // After the CORS listeners (0, -15) and the session listener (-1000), which sets Cache-Control; before
        // ConditionalGetSubscriber (-1024), so a 304 keeps these fields.
        return [KernelEvents::RESPONSE => ['onResponse', -1010]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !ApiPath::matches($request->getPathInfo())) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');

        $allowedOrigin = $headers->get('Access-Control-Allow-Origin');
        if (null !== $allowedOrigin && '*' !== $allowedOrigin) {
            $event->getResponse()->setVary('Origin', false);
        }

        if (in_array($request->getPathInfo(), self::TOKEN_ADDRESSES, true) && $event->getResponse()->isSuccessful()) {
            $headers->set('Cache-Control', 'no-store');
            $headers->set('Pragma', 'no-cache');
        }
    }
}
