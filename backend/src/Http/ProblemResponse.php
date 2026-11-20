<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API's error shape (RFC 9457 Problem Details: "type", "title", "status", "detail" and the extension members of
 * the problem type) for responses built outside API Platform's serializer: authentication failures and errors of
 * routes API Platform does not handle. A client tells the problems apart by "type" (section 3.1.1).
 */
final class ProblemResponse
{
    public const string TYPE_PREFIX = 'urn:finance-app:problem:';

    /** @var array<int, string> */
    private const array HTTP_ERROR_NAMES = [
        400 => 'bad_request',
        401 => 'unauthorized',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        406 => 'not_acceptable',
        409 => 'conflict',
        410 => 'gone',
        412 => 'precondition_failed',
        413 => 'content_too_large',
        415 => 'unsupported_media_type',
        422 => 'unprocessable_content',
        429 => 'too_many_requests',
        500 => 'internal_server_error',
        501 => 'not_implemented',
        502 => 'bad_gateway',
        503 => 'service_unavailable',
        504 => 'gateway_timeout',
    ];

    /** RFC 9457, section 3.1, and "errors" of a validation problem (ProblemNormalizer). */
    private const array STANDARD_MEMBERS = ['type', 'title', 'status', 'detail', 'instance', 'errors'];

    /** RFC 9457, section 4: a letter, then letters, digits and "_", three characters or more. */
    private const string EXTENSION_NAME = '/^[A-Za-z][A-Za-z0-9_]{2,}$/D';

    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * @codeCoverageIgnore The class has only static members: no instance is ever made, so this never runs.
     */
    private function __construct()
    {
    }

    /**
     * @param array<string, string>              $extensions members of this problem type, e.g. ['minutes' => '15']
     * @param array<string, string|list<string>> $headers
     * @param string|null                        $title      the problem type's own title; the HTTP status name if null
     */
    public static function create(
        int $status,
        string $code,
        string $detail,
        array $extensions = [],
        array $headers = [],
        ?string $title = null,
    ): JsonResponse {
        // Header names are case-insensitive: an exception's own "content-type" must not replace ours.
        $headers = array_filter(
            $headers,
            static fn (string $name): bool => 'content-type' !== strtolower($name),
            ARRAY_FILTER_USE_KEY,
        );
        $fields = self::fields($status, $code, $extensions, $title);
        $payload = ['type' => $fields['type'], 'title' => $fields['title'], 'status' => $status, 'detail' => $detail]
            + $fields;

        // Encoded once. Plain JSON like the rest of the API (no \u0022-style escaping for curl users); invalid
        // UTF-8, e.g. in an exception message, is replaced instead of making the error response itself fail.
        $json = json_encode($payload, self::JSON_FLAGS);

        return JsonResponse::fromJsonString(
            $json,
            $status,
            ['Content-Type' => 'application/problem+json'] + $headers,
        );
    }

    /**
     * The members our contract gives every error, also used by ProblemNormalizer for API Platform's errors: the
     * problem type, its title (RFC 9457, section 3.1.3: a summary of the problem type, the HTTP status name for the
     * "http." types) and the extension members of the type (section 3.2), e.g. "minutes" of a locked login.
     *
     * @param array<string, string> $extensions
     *
     * @return array<string, string>
     */
    public static function fields(int $status, string $code, array $extensions, ?string $title = null): array
    {
        foreach (array_keys($extensions) as $name) {
            if (in_array($name, self::STANDARD_MEMBERS, true) || 1 !== preg_match(self::EXTENSION_NAME, $name)) {
                throw new \LogicException(sprintf('"%s" cannot be an extension member of a problem.', $name));
            }
        }

        return ['type' => self::TYPE_PREFIX.$code, 'title' => $title ?? self::title($status)] + $extensions;
    }

    /**
     * An empty ArrayObject is emitted as "{}", an empty array would be "[]".
     *
     * @param array<string, string> $params
     *
     * @return array<string, string>|\ArrayObject<string, string>
     */
    public static function jsonParams(array $params): array|\ArrayObject
    {
        return [] === $params ? new \ArrayObject() : $params;
    }

    public static function title(int $status): string
    {
        return Response::$statusTexts[$status] ?? 'Error';
    }

    /**
     * The code of an error without a business code, the end of its type, e.g. "http.not_found". The names are fixed
     * here, not taken from Symfony's status texts, which change between versions (422 was "Unprocessable Entity"):
     * the codes are part of the API contract. A status not listed gets its number, e.g. "http.418".
     */
    public static function httpErrorCode(int $status): string
    {
        return 'http.'.(self::HTTP_ERROR_NAMES[$status] ?? (string) $status);
    }
}
