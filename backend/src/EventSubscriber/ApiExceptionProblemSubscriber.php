<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\AcceptPatch;
use App\Http\ApiPath;
use App\Http\ProblemResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Errors under /api that API Platform does not handle (POST /api/login_check, unknown /api paths) would get
 * Symfony's generic error page. This answers them in the API's Problem Details shape instead.
 * Requests of API Platform operations are left to API Platform (see ProblemNormalizer).
 *
 * The router has no OPTIONS routes, so an OPTIONS request also ends here, as a 405; it gets its answer instead.
 */
final readonly class ApiExceptionProblemSubscriber implements EventSubscriberInterface
{
    /** The methods of RFC 9110 (section 9.3) and PATCH (RFC 5789); the router refuses any other with a 405. */
    private const array KNOWN_METHODS = [
        'GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'CONNECT', 'OPTIONS', 'TRACE', 'PATCH',
    ];

    public function __construct(private AcceptPatch $acceptPatch)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Below 0: Symfony's ErrorListener logs the exception (and the profiler records it) at priority 0, and
        // setting a response stops the event. Above API Platform (-96) and Symfony's error page (-128).
        return [KernelEvents::EXCEPTION => ['onException', -80]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $isApiPath = ApiPath::matches($path);
        if (!$isApiPath || $request->attributes->has('_api_resource_class')) {
            return;
        }

        // RFC 9110, section 9.1: a method the server does not recognize gets 501, whatever the path. Every /api route
        // names its methods, so such a request always ends here, as a 404 or 405 of the router.
        $method = $request->getMethod();
        if (!in_array($method, self::KNOWN_METHODS, true)) {
            $event->setResponse(ProblemResponse::create(
                Response::HTTP_NOT_IMPLEMENTED,
                ProblemResponse::httpErrorCode(Response::HTTP_NOT_IMPLEMENTED),
                sprintf('%s. The API does not know the method %s.', ProblemResponse::title(501), $method),
            ));

            return;
        }

        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;
        $headers = $exception instanceof HttpExceptionInterface ? $this->headers($exception->getHeaders()) : [];
        $allowed = is_string($headers['Allow'] ?? null) ? $this->supported($headers['Allow']) : null;
        if (null !== $allowed) {
            $headers['Allow'] = $allowed;
            if ('OPTIONS' === $method) {
                $event->allowCustomResponseCode();
                $event->setResponse($this->options($allowed));

                return;
            }
        }

        $event->setResponse(ProblemResponse::create(
            $status,
            ProblemResponse::httpErrorCode($status),
            $this->detail($exception, $status, $allowed),
            headers: $headers,
        ));
    }

    /**
     * The router's list of methods misses HEAD, which it accepts wherever it accepts GET, and OPTIONS, answered here.
     * The Allow header of a 405 must list every method the resource supports (RFC 9110, section 15.5.6).
     */
    private function supported(string $allowed): string
    {
        $methods = array_map(trim(...), explode(',', $allowed));
        $get = array_search('GET', $methods, true);
        if (is_int($get) && !in_array('HEAD', $methods, true)) {
            array_splice($methods, $get + 1, 0, 'HEAD');
        }
        if (!in_array('OPTIONS', $methods, true)) {
            $methods[] = 'OPTIONS';
        }

        return implode(', ', $methods);
    }

    /**
     * RFC 9110, section 9.3.7: the answer names the options of the resource in its header fields, Allow and, where
     * PATCH is allowed, Accept-Patch (RFC 5789, section 3.1). A CORS preflight never gets here: NelmioCorsBundle
     * answers it before the routing.
     */
    private function options(string $allowed): Response
    {
        $headers = ['Allow' => $allowed];
        if (in_array('PATCH', explode(', ', $allowed), true)) {
            $headers['Accept-Patch'] = $this->acceptPatch->value;
        }

        return new Response(null, Response::HTTP_NO_CONTENT, $headers);
    }

    /**
     * Keeps every header an HTTP exception carries, e.g. an int Retry-After of a 429 or 503.
     *
     * @param array<mixed> $headers
     *
     * @return array<string, string|list<string>>
     */
    private function headers(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            if (is_scalar($value)) {
                $result[(string) $name] = (string) $value;
            } elseif (is_array($value)) {
                $result[(string) $name] = array_values(array_map(
                    static fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
                    $value,
                ));
            }
        }

        return $result;
    }

    private function detail(\Throwable $exception, int $status, ?string $allowed): string
    {
        // Never the cause of a server error, not even in debug mode: "detail" helps the client, not the debugging
        // (RFC 9457, section 3.1.4); the log and the profiler have the exception.
        if ($status >= 500) {
            return ProblemResponse::title($status);
        }

        // The router's messages describe the routing internals; the status says enough.
        if ($exception instanceof NotFoundHttpException || $exception instanceof MethodNotAllowedHttpException) {
            return ProblemResponse::title($status).(is_string($allowed) ? '. Allowed: '.$allowed.'.' : '.');
        }

        // Other client errors here come from the json_login authenticator and ApiLoginCheckController:
        // short, safe explanations such as 'The key "password" must be provided.' An empty one gets the status.
        $message = trim($exception->getMessage());

        return '' !== $message ? $message : ProblemResponse::title($status).'.';
    }
}
