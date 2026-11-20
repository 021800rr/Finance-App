<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Evaluates If-Match and If-None-Match (RFC 9110, section 13), in the order of section 13.2.2: If-Match first.
 * If-Unmodified-Since and If-Modified-Since need a Last-Modified date, which the API does not send, so there is
 * nothing to compare them with.
 *
 * The entity tag is a hash of the GET response (API Platform's AddHeadersProcessor), and a representation holds more
 * than its own record: an account's balances come from its operations and today's date. So for a request other than
 * GET or HEAD the current representation is the response of a GET of the same address with the client's headers,
 * made as a sub-request, only when the request carries one of the headers.
 */
final readonly class Preconditions
{
    /** Request headers that do not select a representation, but would make the GET conditional or carry a body. */
    private const array NOT_FOR_THE_GET = [
        'CONTENT_TYPE', 'CONTENT_LENGTH', 'HTTP_CONTENT_TYPE', 'HTTP_CONTENT_LENGTH', 'HTTP_IF_MATCH',
        'HTTP_IF_NONE_MATCH', 'HTTP_IF_MODIFIED_SINCE', 'HTTP_IF_UNMODIFIED_SINCE', 'HTTP_IF_RANGE', 'HTTP_RANGE',
    ];

    public function __construct(private HttpKernelInterface $kernel)
    {
    }

    /**
     * For a method other than GET or HEAD: why it must be refused with 412 and not performed, or null when it may go
     * on. The caller has made the checks that come first (section 13.2.1): the address exists, the content type fits.
     */
    public function refusal(Request $request): ?string
    {
        if (!$request->headers->has('If-Match') && !$request->headers->has('If-None-Match')) {
            return null;
        }
        $current = $this->currentRepresentation($request);

        return match (true) {
            self::ifMatchFails($request, $current) => 'If-Match does not match the current representation, '
                .'so nothing was changed.',
            self::ifNoneMatchFails($request, $current) => 'If-None-Match matches the current representation, '
                .'so nothing was changed.',
            default => null,
        };
    }

    /**
     * Section 13.1.1: true when If-Match is sent and neither "*" with a current representation nor one of its entity
     * tags matches the current one, by the strong comparison (section 8.8.3.2: a weak tag never matches).
     *
     * @param Response|null $current the current representation, null when there is none
     */
    public static function ifMatchFails(Request $request, ?Response $current): bool
    {
        $field = $request->headers->get('If-Match');
        if (null === $field) {
            return false;
        }

        $etag = $current?->getEtag();
        foreach (self::tags($field) as $tag) {
            if ('*' === $tag ? null !== $current : null !== $etag && !str_starts_with($etag, 'W/') && $tag === $etag) {
                return false;
            }
        }

        return true;
    }

    /**
     * Section 13.1.2: true when If-None-Match is "*" and there is a current representation, or one of its entity tags
     * matches the current one by the weak comparison (only the opaque tags must be equal).
     *
     * @param Response|null $current the current representation, null when there is none
     */
    public static function ifNoneMatchFails(Request $request, ?Response $current): bool
    {
        $field = $request->headers->get('If-None-Match');
        if (null === $field || null === $current) {
            return false;
        }

        $etag = $current->getEtag();
        foreach (self::tags($field) as $tag) {
            if ('*' === $tag || (null !== $etag && self::opaque($tag) === self::opaque($etag))) {
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
     * @return list<string>
     */
    private static function tags(string $field): array
    {
        return preg_split('/\s*,\s*/', trim($field), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function opaque(string $tag): string
    {
        return str_starts_with($tag, 'W/') ? substr($tag, 2) : $tag;
    }
}
