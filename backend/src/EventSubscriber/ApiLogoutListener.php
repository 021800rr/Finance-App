<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\ProblemResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * POST /api/token/invalidate. The refresh token bundle answers a logout in its own format: 200 with a message, also
 * for a token that is already invalid, or 400 when the request carries none. A logout becomes 204 No Content, the
 * missing token a Problem Details error like every other one. The cookies the logout clears are kept.
 *
 * Only the "api" firewall: the web logout keeps its redirect to the login page.
 */
#[AsEventListener(event: LogoutEvent::class, dispatcher: 'security.event_dispatcher.api', priority: -64)]
final readonly class ApiLogoutListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $original = $event->getResponse();
        if (null === $original) {
            return;
        }

        $response = Response::HTTP_BAD_REQUEST === $original->getStatusCode()
            ? ProblemResponse::create(
                Response::HTTP_BAD_REQUEST,
                'auth.refresh_token_missing',
                'Refresh token not found. Send it in the refresh_token cookie or as {"refresh_token": "<token>"}.',
            )
            : new Response(null, Response::HTTP_NO_CONTENT);

        foreach ($original->headers->getCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }
        $event->setResponse($response);
    }
}
