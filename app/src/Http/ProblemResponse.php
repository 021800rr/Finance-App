<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API's error shape (RFC 9457 Problem Details with "errorCode" and "params") for responses built outside
 * API Platform's serializer: authentication failures and errors of routes API Platform does not handle.
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
        413 => 'content_too_large',
        415 => 'unsupported_media_type',
        422 => 'unprocessable_content',
        429 => 'too_many_requests',
        500 => 'internal_server_error',
        502 => 'bad_gateway',
        503 => 'service_unavailable',
        504 => 'gateway_timeout',
    ];

    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE;

    private function __construct()
    {
    }

    /**
     * @param array<string, string>              $params
     * @param array<string, string|list<string>> $headers
     */
    public static function create(
        int $status,
        string $errorCode,
        string $detail,
        array $params = [],
        array $headers = [],
    ): JsonResponse {
        // Header names are case-insensitive: an exception's own "content-type" must not replace ours.
        $headers = array_filter(
            $headers,
            static fn (string $name): bool => 'content-type' !== strtolower($name),
            ARRAY_FILTER_USE_KEY,
        );
        $fields = self::fields($status, $errorCode, $params);
        $payload = [
            'type' => $fields['type'],
            'title' => $fields['title'],
            'status' => $status,
            'detail' => $detail,
            'errorCode' => $fields['errorCode'],
            'params' => $fields['params'],
        ];

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
     * The fields our contract adds to every error, also used by ProblemNormalizer for API Platform's errors.
     *
     * @param array<string, string> $params
     *
     * @return array{
     *     type: string,
     *     title: string,
     *     errorCode: string,
     *     params: array<string, string>|\ArrayObject<string, string>,
     * }
     */
    public static function fields(int $status, string $errorCode, array $params): array
    {
        return [
            'type' => self::TYPE_PREFIX.$errorCode,
            'title' => self::title($status),
            'errorCode' => $errorCode,
            'params' => self::jsonParams($params),
        ];
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
     * Code of an error without a business code, e.g. "http.not_found". The names are fixed here, not taken from
     * Symfony's status texts, which change between versions (422 was "Unprocessable Entity"): the codes are part
     * of the API contract. A status not listed gets its number, e.g. "http.418".
     */
    public static function httpErrorCode(int $status): string
    {
        return 'http.'.(self::HTTP_ERROR_NAMES[$status] ?? (string) $status);
    }
}
