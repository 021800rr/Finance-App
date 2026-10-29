<?php

declare(strict_types=1);

namespace App\OpenApi;

/**
 * OpenAPI "content" for error responses that come in more than one shape, for use in resource attributes.
 */
final class ErrorResponseContent
{
    /**
     * A 422 that is either a validation error (with "violations") or a refused business rule (without them).
     * "anyOf", not "oneOf": both schemas allow extra properties, so a body matches both and "oneOf" would fail.
     */
    public const array VALIDATION_OR_BUSINESS_ERROR = [
        'application/ld+json' => ['schema' => ['anyOf' => [
            ['$ref' => '#/components/schemas/ConstraintViolation.jsonld'],
            ['$ref' => '#/components/schemas/Error.jsonld'],
        ]]],
        'application/problem+json' => ['schema' => ['anyOf' => [
            ['$ref' => '#/components/schemas/ConstraintViolation'],
            ['$ref' => '#/components/schemas/Error'],
        ]]],
        'application/json' => ['schema' => ['anyOf' => [
            ['$ref' => '#/components/schemas/ConstraintViolation'],
            ['$ref' => '#/components/schemas/Error'],
        ]]],
    ];

    private function __construct()
    {
    }
}
