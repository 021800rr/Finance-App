<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\ProblemResponse;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshAuthenticationFailureEvent;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenNotFoundEvent;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\MissingTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * The JWT and refresh token bundles answer authentication failures before API Platform sees the request, in their
 * own format ({"code": 401, "message": "..."}). This gives them the same Problem Details shape as every other API
 * error: "errorCode", "params" and an English "detail". Status, headers (WWW-Authenticate etc.) and cookies are kept.
 */
final readonly class AuthenticationProblemSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_NOT_FOUND => 'onTokenNotFound',
            Events::JWT_INVALID => 'onTokenInvalid',
            Events::JWT_EXPIRED => 'onTokenExpired',
            Events::AUTHENTICATION_FAILURE => 'onLoginFailure',
            'gesdinet.refresh_token_not_found' => 'onRefreshTokenNotFound',
            'gesdinet.refresh_token_failure' => 'onRefreshFailure',
        ];
    }

    public function onTokenNotFound(JWTNotFoundEvent $event): void
    {
        $event->setResponse($this->problem(
            $event->getResponse(),
            'auth.token_missing',
            'JWT Token not found. Send it as "Authorization: Bearer <token>" or in the BEARER cookie; '
            .'get one from POST /api/login_check.',
        ));
    }

    public function onTokenInvalid(JWTInvalidEvent $event): void
    {
        $event->setResponse($this->problem($event->getResponse(), 'auth.token_invalid', 'Invalid JWT Token.'));
    }

    public function onTokenExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse($this->problem(
            $event->getResponse(),
            'auth.token_expired',
            'Expired JWT Token. Get a new one from POST /api/token/refresh, '
            .'or log in again with POST /api/login_check.',
        ));
    }

    public function onLoginFailure(AuthenticationFailureEvent $event): void
    {
        $exception = $event->getException();
        $response = $event->getResponse();
        // The bundle's own message is already translated (English for /api) and fits each exception.
        $detail = $response instanceof JWTAuthenticationFailureResponse ? $response->getMessage() : 'Login failed.';

        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $minutes = $exception->getMessageData()['%minutes%'] ?? null;
            $params = is_numeric($minutes) ? ['minutes' => (string) $minutes] : [];
            $problem = $this->problem($response, 'auth.too_many_login_attempts', $detail, $params);
            // The status stays 401 (as the bundle sends it); Retry-After lets generic clients back off.
            if (is_numeric($minutes)) {
                $problem->headers->set('Retry-After', (string) ((int) ceil((float) $minutes * 60)));
            }
            $event->setResponse($problem);

            return;
        }

        $errorCode = $exception instanceof BadCredentialsException ? 'auth.invalid_credentials' : 'auth.login_failed';
        $event->setResponse($this->problem($response, $errorCode, $detail));
    }

    public function onRefreshTokenNotFound(RefreshTokenNotFoundEvent $event): void
    {
        $event->setResponse($this->refreshTokenMissing($event->getResponse()));
    }

    /**
     * Unknown, expired and already used refresh tokens are told apart only in the logs: a client can do one thing
     * about each, log in again.
     */
    public function onRefreshFailure(RefreshAuthenticationFailureEvent $event): void
    {
        $event->setResponse($event->getException() instanceof MissingTokenException
            ? $this->refreshTokenMissing($event->getResponse())
            : $this->problem(
                $event->getResponse(),
                'auth.refresh_token_invalid',
                'Invalid, expired or already used refresh token. Log in again with POST /api/login_check.',
            ));
    }

    private function refreshTokenMissing(?Response $original): Response
    {
        return $this->problem(
            $original,
            'auth.refresh_token_missing',
            'Refresh token not found. Send it in the refresh_token cookie or as {"refresh_token": "<token>"}.',
        );
    }

    /**
     * @param array<string, string> $params
     */
    private function problem(?Response $original, string $errorCode, string $detail, array $params = []): Response
    {
        // Every header and cookie of the bundle's response (WWW-Authenticate and anything added by other
        // listeners) is kept; ProblemResponse sets its own Content-Type.
        $headers = [];
        foreach ($original?->headers->allPreserveCaseWithoutCookies() ?? [] as $name => $values) {
            $headers[(string) $name] = array_values(array_filter(is_array($values) ? $values : [], 'is_string'));
        }

        $problem = ProblemResponse::create(
            $original?->getStatusCode() ?? Response::HTTP_UNAUTHORIZED,
            $errorCode,
            $detail,
            $params,
            $headers,
        );
        foreach ($original?->headers->getCookies() ?? [] as $cookie) {
            $problem->headers->setCookie($cookie);
        }

        return $problem;
    }
}
