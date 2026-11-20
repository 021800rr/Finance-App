<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Evaluates If-None-Match on a request that changes something (RFC 9110, section 13.1.2): when it is "*" and the
 * target has a current representation, or it names that representation's entity tag, the request is refused with
 * 412 and nothing changes. GET and HEAD get a 304 instead (NotModifiedSubscriber).
 *
 * The entity tag is a hash of the GET response (API Platform's AddHeadersProcessor), and a representation holds more
 * than its own record: an account's balances come from its operations and today's date. So the current entity tag
 * is the one of a GET of the same address with the client's headers, made as a sub-request, only when the request
 * carries the header.
 *
 * @implements ProviderInterface<object>
 */
final readonly class IfNoneMatchProvider implements ProviderInterface
{
    /** Request headers that do not select a representation, but would make the GET conditional or carry a body. */
    private const array NOT_FOR_THE_GET = [
        'CONTENT_TYPE', 'CONTENT_LENGTH', 'HTTP_CONTENT_TYPE', 'HTTP_CONTENT_LENGTH', 'HTTP_IF_MATCH',
        'HTTP_IF_NONE_MATCH', 'HTTP_IF_MODIFIED_SINCE', 'HTTP_IF_UNMODIFIED_SINCE', 'HTTP_IF_RANGE', 'HTTP_RANGE',
    ];

    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(private ProviderInterface $decorated, private HttpKernelInterface $kernel)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return object|array<mixed>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // After the record is loaded: errors found before the request content is processed come first, so a missing
        // record stays 404 (section 13.2.1).
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        $request = $context['request'] ?? null;
        if (
            $request instanceof Request
            && !$request->isMethodSafe()
            && $request->headers->has('If-None-Match')
            // A missing or unsupported Content-Type, which API Platform answers with 415, comes first as well.
            && !($operation instanceof HttpOperation && $operation->canDeserialize()
                && null === $request->attributes->get('input_format'))
            && $this->conditionFails($request)
        ) {
            throw new PreconditionFailedHttpException('If-None-Match matches the current representation, so nothing was changed.');
        }

        return $data;
    }

    private function conditionFails(Request $request): bool
    {
        $current = $this->currentRepresentation($request);
        if (null === $current) {
            return false;
        }

        $etag = $current->getEtag();
        foreach ($request->getETags() as $tag) {
            if ('*' === $tag || (is_string($tag) && null !== $etag && self::opaque($tag) === self::opaque($etag))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The response of a GET of the request's address, or null when there is none to give (an error or a redirect).
     */
    private function currentRepresentation(Request $request): ?Response
    {
        $server = array_diff_key($request->server->all(), array_flip(self::NOT_FOR_THE_GET));
        $get = Request::create($request->getUri(), 'GET', cookies: $request->cookies->all(), server: $server);
        $response = $this->kernel->handle($get, HttpKernelInterface::SUB_REQUEST);

        return $response->isSuccessful() ? $response : null;
    }

    /**
     * If-None-Match uses the weak comparison (section 8.8.3.2): only the opaque tags must match.
     */
    private static function opaque(string $tag): string
    {
        return str_starts_with($tag, 'W/') ? substr($tag, 2) : $tag;
    }
}
