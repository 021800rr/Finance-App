<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use App\Http\ProblemResponse;

/**
 * Documents the error contract of ProblemNormalizer, ProblemResponse and ViolationCodes (RFC 9457 Problem Details):
 * the problem type and its title, the extension members of the type, and "errors" of a validation problem with a
 * stable "code" and "params" per item.
 */
final readonly class ProblemDetailsDecorator implements OpenApiFactoryInterface
{
    /** Errors are plain Problem Details whatever the Accept header (error_formats), so no JSON-LD schemas. */
    private const array ERROR_SCHEMAS = ['Error', 'ConstraintViolation'];

    private const string TYPE_DESCRIPTION = 'The problem type: "'.ProblemResponse::TYPE_PREFIX.'" and its code, e.g. '
        .'"transfer.accounts_must_differ", "validation.failed" or "http.not_found". A client tells the problems '
        .'apart by it (RFC 9457, section 3.1.1).';

    private const string NOT_MODIFIED_DESCRIPTION = 'Not modified: If-None-Match names the entity tag (ETag) of the '
        .'current representation, so the client\'s copy is still current. No content (RFC 9110, section 13.1.2).';

    private const string PRECONDITION_DESCRIPTION = 'Precondition failed, nothing was done: If-Match does not name the '
        .'entity tag (ETag) of the current representation, or If-None-Match does (any method but GET and HEAD) '
        .'(type http.precondition_failed; RFC 9110, sections 13.1.1 and 13.1.2).';

    private const array CHALLENGE = ['WWW-Authenticate' => [
        'description' => '"Bearer" without a token, \'Bearer error="invalid_token"\' with a refused one (RFC 6750).',
        'schema' => ['type' => 'string'],
    ]];

    private const string AUTH_ERROR_DESCRIPTION = 'The JWT token is missing, invalid or expired '
        .'(type auth.token_missing, auth.token_invalid or auth.token_expired).';

    private const string LOGIN_ERROR_DESCRIPTION = 'Wrong email or password (type auth.invalid_credentials), '
        .'or the request carries no email or password (type http.bad_request).';

    private const string LOGIN_LOCK_DESCRIPTION = 'The login is locked after too many failed attempts '
        .'(type auth.too_many_login_attempts, member minutes): every password is refused until the lock ends.';

    private const string REFRESH_ERROR_DESCRIPTION = 'The refresh token is missing, or invalid, expired or already '
        .'used (type auth.refresh_token_missing or auth.refresh_token_invalid). Log in again.';

    private const string LOGOUT_ERROR_DESCRIPTION = 'The request carries no refresh token '
        .'(type auth.refresh_token_missing).';

    private const string CONTRACTOR_RACE_DESCRIPTION = 'Another request stored the same new contractorName at the '
        .'same moment (type contractor.name_not_unique). Nothing was stored; send the request again.';

    /** "METHOD path" => what the conflict means */
    private const array CONFLICTS = [
        'POST /api/contractors' => 'A contractor with this name already exists (type contractor.name_not_unique).',
        'POST /api/account_operations' => self::CONTRACTOR_RACE_DESCRIPTION,
        'PATCH /api/account_operations/{id}' => self::CONTRACTOR_RACE_DESCRIPTION,
        'POST /api/fixed_expenses' => self::CONTRACTOR_RACE_DESCRIPTION,
        'PATCH /api/fixed_expenses/{id}' => self::CONTRACTOR_RACE_DESCRIPTION,
        'PATCH /api/accounts/{id}' => 'Another request changed the main account at the same moment '
            .'(type account.main_changed_concurrently).',
        'POST /api/fixed_expenses/plan' => 'The expenses cannot be planned (type fixed_expense.plan_*). '
            .'Nothing was created.',
    ];

    /** "METHOD path" => which query parameters it refuses with 400 */
    private const array INVALID_QUERIES = [
        'GET /api/account_operations' => 'Validation failed: q is missing, blank, too long or contains a NUL '
            .'character, or page is not a positive whole number (type validation.failed).',
        'GET /api/accounts/{accountId}/operations' => 'Validation failed: page is not a positive whole number '
            .'(type validation.failed).',
        'GET /api/contractors' => 'Validation failed: name is blank, too long or contains a NUL character '
            .'(type validation.failed).',
    ];

    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        // API Platform's schemas describe its own error shape; ours replace them.
        $schemas = $openApi->getComponents()->getSchemas();
        if (null !== $schemas) {
            $schemas['Error'] = new \ArrayObject(self::errorSchema());
            $schemas['ConstraintViolation'] = new \ArrayObject(self::violationSchema());
        }

        // Paths::addPath() takes nothing but a PathItem; getPaths() just does not say so in its type.
        /** @var array<string, PathItem> $pathItems */
        $pathItems = $openApi->getPaths()->getPaths();
        foreach ($pathItems as $path => $pathItem) {
            $pathItem = $this->withErrors($pathItem, $path);
            $openApi->getPaths()->addPath($path, $this->withStatusExamples($pathItem, $path));
        }

        return $openApi;
    }

    /**
     * Errors that API Platform cannot know about: 401 and the refused login or refresh (400) come from the firewall,
     * 409 from the processors, and 400 of a GET from the query parameters, which API Platform validates without
     * documenting it.
     */
    private function withErrors(PathItem $pathItem, string $path): PathItem
    {
        return $pathItem
            ->withGet($this->withOperationErrors($pathItem->getGet(), 'GET '.$path))
            ->withPost($this->withOperationErrors($pathItem->getPost(), 'POST '.$path))
            ->withPatch($this->withOperationErrors($pathItem->getPatch(), 'PATCH '.$path))
            ->withDelete($this->withOperationErrors($pathItem->getDelete(), 'DELETE '.$path));
    }

    private function withOperationErrors(?Operation $operation, string $route): ?Operation
    {
        if (null === $operation) {
            return null;
        }

        $responses = $operation->getResponses() ?? [];
        if ([] !== ($operation->getSecurity() ?? [])) {
            $responses['401'] = $this->errorResponse(self::AUTH_ERROR_DESCRIPTION, self::CHALLENGE);
        }
        // Conditional requests (Preconditions): every GET answers with an ETag.
        if (str_starts_with($route, 'GET ')) {
            $responses['304'] = new Response(self::NOT_MODIFIED_DESCRIPTION);
        }
        $responses['412'] = $this->errorResponse(self::PRECONDITION_DESCRIPTION);
        if ('POST /api/login_check' === $route) {
            $responses['400'] = $this->errorResponse(self::LOGIN_ERROR_DESCRIPTION);
            $responses['429'] = $this->errorResponse(self::LOGIN_LOCK_DESCRIPTION, [
                'Retry-After' => [
                    'description' => 'Seconds until the lock ends.',
                    'schema' => ['type' => 'integer'],
                ],
            ]);
        }
        if ('POST /api/token/refresh' === $route) {
            // The refresh bundle documents its own 401, which AuthenticationProblemSubscriber turns into this 400.
            unset($responses['401']);
            $responses['400'] = $this->errorResponse(self::REFRESH_ERROR_DESCRIPTION);
        }
        if ('POST /api/token/invalidate' === $route) {
            $responses['400'] = $this->errorResponse(self::LOGOUT_ERROR_DESCRIPTION);
        }
        if (isset(self::CONFLICTS[$route])) {
            $responses['409'] = $this->errorResponse(self::CONFLICTS[$route]);
        }
        if (isset(self::INVALID_QUERIES[$route])) {
            $responses['400'] = $this->violationResponse(self::INVALID_QUERIES[$route]);
        }
        ksort($responses);

        return $operation->withResponses($responses);
    }

    private function violationResponse(string $description): Response
    {
        return new Response($description, new \ArrayObject([
            'application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/ConstraintViolation']],
        ]));
    }

    /**
     * @param array<string, mixed> $headers response headers by name, as OpenAPI Header Objects
     */
    private function errorResponse(string $description, array $headers = []): Response
    {
        return new Response($description, new \ArrayObject([
            'application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/Error']],
        ]), [] === $headers ? null : new \ArrayObject($headers));
    }

    /**
     * All error responses share one schema, so Swagger UI would show the same example ("status": 404) for every
     * one of them. Each error response gets its own status and a matching example type instead.
     */
    private function withStatusExamples(PathItem $pathItem, string $path): PathItem
    {
        return $pathItem
            ->withGet($this->withOperationExamples($pathItem->getGet(), 'GET '.$path))
            ->withPost($this->withOperationExamples($pathItem->getPost(), 'POST '.$path))
            ->withPut($this->withOperationExamples($pathItem->getPut(), 'PUT '.$path))
            ->withPatch($this->withOperationExamples($pathItem->getPatch(), 'PATCH '.$path))
            ->withDelete($this->withOperationExamples($pathItem->getDelete(), 'DELETE '.$path));
    }

    private function withOperationExamples(?Operation $operation, string $route): ?Operation
    {
        if (null === $operation || null === $operation->getResponses()) {
            return $operation;
        }

        $responses = [];
        foreach ($operation->getResponses() as $status => $response) {
            $responses[$status] = $response instanceof Response && (int) $status >= 400
                ? $this->withStatusExample($response, (int) $status, $route)
                : $response;
        }

        return $operation->withResponses($responses);
    }

    private function withStatusExample(Response $response, int $status, string $route): Response
    {
        $content = $response->getContent();
        if (null === $content) {
            return $response;
        }

        /** @var \ArrayObject<string, mixed> $documented */
        $documented = new \ArrayObject();
        foreach ($content as $mediaType => $media) {
            // Every error is application/problem+json, whatever the client accepts (error_formats).
            if ('application/problem+json' !== $mediaType && isset($content['application/problem+json'])) {
                continue;
            }
            // Responses set in resource attributes (e.g. ErrorResponseContent) are plain arrays, not MediaType.
            $schema = $this->toArray(match (true) {
                $media instanceof MediaType => $media->getSchema(),
                is_array($media) => $media['schema'] ?? null,
                default => null,
            });
            if (!is_array($schema) || !$this->isErrorSchema($schema)) {
                $documented[(string) $mediaType] = $media;
                continue;
            }
            $withExample = ['allOf' => [
                $schema,
                ['type' => 'object', 'properties' => [
                    'status' => ['type' => 'integer', 'const' => $status, 'examples' => [$status]],
                    'type' => [
                        'type' => 'string',
                        'examples' => [ProblemResponse::TYPE_PREFIX.$this->exampleCode($status, $route)],
                    ],
                ]],
            ]];
            $documented[(string) $mediaType] = $media instanceof MediaType
                ? $media->withSchema(new \ArrayObject($withExample))
                : ['schema' => $withExample] + (is_array($media) ? $media : []);
        }

        return $response->withContent($documented);
    }

    /**
     * A reference to one of the error schemas, or a choice ("anyOf") between them.
     *
     * @param array<mixed> $schema
     */
    private function isErrorSchema(array $schema): bool
    {
        $refs = isset($schema['$ref']) ? [$schema] : $schema['anyOf'] ?? null;
        if (!is_array($refs) || [] === $refs) {
            return false;
        }
        foreach ($refs as $ref) {
            $ref = $this->toArray($ref);
            $name = is_array($ref) && is_string($ref['$ref'] ?? null) ? basename($ref['$ref']) : null;
            if (!in_array($name, self::ERROR_SCHEMAS, true)) {
                return false;
            }
        }

        return true;
    }

    private function exampleCode(int $status, string $route): string
    {
        return match (true) {
            400 === $status && 'POST /api/login_check' === $route => 'auth.invalid_credentials',
            429 === $status && 'POST /api/login_check' === $route => 'auth.too_many_login_attempts',
            400 === $status && 'POST /api/token/refresh' === $route => 'auth.refresh_token_invalid',
            400 === $status && 'POST /api/token/invalidate' === $route => 'auth.refresh_token_missing',
            401 === $status => 'auth.token_missing',
            422 === $status, 400 === $status && isset(self::INVALID_QUERIES[$route]) => 'validation.failed',
            default => ProblemResponse::httpErrorCode($status),
        };
    }

    /**
     * Every error (ProblemResponse, ProblemNormalizer): the members of RFC 9457 and the extension members of the
     * problem type, all strings, e.g. "minutes" of a locked login or "fromCurrency" and "toCurrency" of a transfer.
     *
     * @param array<string, mixed> $moreProperties
     *
     * @return array<string, mixed>
     */
    private static function errorSchema(
        string $description = 'A problem (RFC 9457). Other members than these belong to the problem type and are '
            .'strings, e.g. "minutes" of auth.too_many_login_attempts.',
        array $moreProperties = [],
    ): array {
        return [
            'type' => 'object',
            'description' => $description,
            'properties' => [
                'type' => ['type' => 'string', 'readOnly' => true, 'description' => self::TYPE_DESCRIPTION],
                'title' => [
                    'type' => 'string',
                    'readOnly' => true,
                    'description' => 'A short summary of the problem type, e.g. "Validation failed"; the name of the '
                        .'HTTP status for the "http." types.',
                ],
                // API Platform's own example (404) would show up on every error response.
                'status' => ['type' => 'integer', 'readOnly' => true, 'description' => 'The HTTP status code.'],
                'detail' => [
                    'type' => 'string',
                    'readOnly' => true,
                    'description' => 'This occurrence in English, for people (curl, Postman).',
                ],
            ] + $moreProperties,
            'additionalProperties' => ['type' => 'string'],
            'required' => ['type', 'title', 'status', 'detail', ...array_keys($moreProperties)],
        ];
    }

    /**
     * A validation problem ("validation.failed"): the error members and "errors", one item per refused value.
     *
     * @return array<string, mixed>
     */
    private static function violationSchema(): array
    {
        return self::errorSchema(
            'A validation problem (type "'.ProblemResponse::TYPE_PREFIX.'validation.failed").',
            ['errors' => [
                'type' => 'array',
                'readOnly' => true,
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'detail' => ['type' => 'string', 'description' => 'The refusal in English.'],
                        'pointer' => [
                            'type' => 'string',
                            'description' => 'The refused value in the content, as a JSON Pointer (RFC 6901) in its '
                                .'URI fragment form, e.g. "#/amountFrom". Not with "parameter".',
                        ],
                        'parameter' => [
                            'type' => 'string',
                            'description' => 'The refused query parameter, e.g. "page". Not with "pointer".',
                        ],
                        'code' => [
                            'type' => 'string',
                            'description' => 'Stable code of the refusal, e.g. "transfer.to_account_required" or '
                                .'"validation.invalid_type".',
                        ],
                        'params' => [
                            'type' => 'object',
                            'additionalProperties' => ['type' => 'string'],
                            'description' => 'Values for the message: only "limit", "min", "max", "type" (a JSON type, '
                                .'e.g. "number|null"); never the submitted value.',
                        ],
                    ],
                    'required' => ['detail', 'code', 'params'],
                ],
            ]],
        );
    }

    /**
     * API Platform keeps some schema parts as ArrayObject instead of arrays.
     */
    private function toArray(mixed $value): mixed
    {
        return $value instanceof \ArrayObject ? $value->getArrayCopy() : $value;
    }
}
