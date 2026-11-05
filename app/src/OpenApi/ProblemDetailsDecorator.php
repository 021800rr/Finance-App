<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use App\Http\ProblemResponse;

/**
 * Documents the error contract added by ProblemNormalizer and ViolationCodes: "errorCode" and "params"
 * on every error, a stable "code" and "params" on every violation, and the list of business error codes.
 */
final readonly class ProblemDetailsDecorator implements OpenApiFactoryInterface
{
    private const array ERROR_SCHEMAS = ['Error', 'Error.jsonld', 'ConstraintViolation', 'ConstraintViolation.jsonld'];

    private const string ERROR_ADDRESS_RESPONSE = 'The general description of this HTTP status. The response has '
        .'status 200: this address describes an error, it does not report one.';

    private const string ERROR_ADDRESS_DESCRIPTION = <<<'MD'
        Send your JWT token, as for every other address:

        ```bash
        BASE_URL=http://localhost:8081 # the address of your instance
        curl "$BASE_URL/api/errors/409" \
          -H "Authorization: Bearer $TOKEN" \
          -H 'Accept: application/ld+json'
        ```

        It describes only the HTTP status (`errorCode` `http.conflict` for 409), not the specific problem.
        MD;

    private const string AUTH_ERROR_DESCRIPTION = 'The JWT token is missing, invalid or expired '
        .'(errorCode auth.token_missing, auth.token_invalid or auth.token_expired).';

    private const string LOGIN_ERROR_DESCRIPTION = 'Wrong email or password, or the login is locked after too many '
        .'failed attempts (errorCode auth.invalid_credentials or auth.too_many_login_attempts).';

    private const string CONTRACTOR_RACE_DESCRIPTION = 'Another request stored the same new contractorName at the '
        .'same moment (errorCode contractor.name_not_unique). Nothing was stored; send the request again.';

    /** "METHOD path" => what the conflict means */
    private const array CONFLICTS = [
        'POST /api/contractors' => 'A contractor with this name already exists (errorCode contractor.name_not_unique).',
        'POST /api/account_operations' => self::CONTRACTOR_RACE_DESCRIPTION,
        'PATCH /api/account_operations/{id}' => self::CONTRACTOR_RACE_DESCRIPTION,
        'POST /api/fixed_expenses' => self::CONTRACTOR_RACE_DESCRIPTION,
        'PATCH /api/fixed_expenses/{id}' => self::CONTRACTOR_RACE_DESCRIPTION,
        'PATCH /api/accounts/{id}' => 'Another request changed the main account at the same moment '
            .'(errorCode account.main_changed_concurrently).',
        'POST /api/fixed_expenses/plan' => 'The expenses cannot be planned (errorCode fixed_expense.plan_*). '
            .'Nothing was created.',
    ];

    /** "METHOD path" => which query parameters it refuses with 422 */
    private const array INVALID_QUERIES = [
        'GET /api/account_operations' => 'Validation failed: q is missing, blank, too long or contains a NUL '
            .'character, or page is not a positive whole number (errorCode validation.failed).',
        'GET /api/accounts/{accountId}/operations' => 'Validation failed: page is not a positive whole number '
            .'(errorCode validation.failed).',
        'GET /api/contractors' => 'Validation failed: name is blank, too long or contains a NUL character '
            .'(errorCode validation.failed).',
    ];

    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $schemas = $openApi->getComponents()->getSchemas();
        if (null !== $schemas) {
            foreach (self::ERROR_SCHEMAS as $name) {
                if (isset($schemas[$name]) && $schemas[$name] instanceof \ArrayObject) {
                    $schemas[$name]->exchangeArray($this->withErrorCode($schemas[$name]->getArrayCopy()));
                }
            }
        }

        // API Platform provides this address, but leaves it out of the documentation.
        $openApi->getPaths()->addPath('/api/errors/{status}', new PathItem(get: new Operation(
            operationId: 'api_errors_get',
            tags: ['Error'],
            responses: ['200' => new Response(self::ERROR_ADDRESS_RESPONSE, new \ArrayObject([
                'application/ld+json' => ['schema' => ['$ref' => '#/components/schemas/Error.jsonld']],
                'application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']],
            ]))],
            summary: 'Describes an HTTP error status. This is the address in the "@id" of an error.',
            description: self::ERROR_ADDRESS_DESCRIPTION,
            parameters: [new Parameter(
                name: 'status',
                in: 'path',
                description: 'HTTP status of the error, e.g. 409.',
                required: true,
                schema: ['type' => 'integer'],
                example: 409,
            )],
            security: [['JWT' => []]],
        )));

        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            if ($pathItem instanceof PathItem) {
                $pathItem = $this->withErrors($pathItem, $path);
                $openApi->getPaths()->addPath($path, $this->withStatusExamples($pathItem, $path));
            }
        }

        return $openApi;
    }

    /**
     * Errors that API Platform cannot know about: 401 comes from the firewall, 409 from the processors, and 422 of
     * a GET from the query parameters, which API Platform validates without documenting it.
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
            $responses['401'] = $this->errorResponse(self::AUTH_ERROR_DESCRIPTION);
        }
        if ('POST /api/login_check' === $route) {
            $responses['401'] = $this->errorResponse(self::LOGIN_ERROR_DESCRIPTION);
        }
        if (isset(self::CONFLICTS[$route])) {
            $responses['409'] = $this->errorResponse(self::CONFLICTS[$route]);
        }
        if (isset(self::INVALID_QUERIES[$route])) {
            $responses['422'] = $this->violationResponse(self::INVALID_QUERIES[$route]);
        }
        ksort($responses);

        return $operation->withResponses($responses);
    }

    private function violationResponse(string $description): Response
    {
        return new Response($description, new \ArrayObject([
            'application/ld+json' => ['schema' => ['$ref' => '#/components/schemas/ConstraintViolation.jsonld']],
            'application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/ConstraintViolation']],
            'application/json' => ['schema' => ['$ref' => '#/components/schemas/ConstraintViolation']],
        ]));
    }

    private function errorResponse(string $description): Response
    {
        return new Response($description, new \ArrayObject([
            'application/ld+json' => ['schema' => ['$ref' => '#/components/schemas/Error.jsonld']],
            'application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/Error']],
            'application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']],
        ]));
    }

    /**
     * All error responses share one schema, so Swagger UI would show the same example ("status": 404) for every
     * one of them. Each error response gets its own status and a matching example errorCode instead.
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
                    'errorCode' => ['type' => 'string', 'examples' => [$this->exampleErrorCode($status, $route)]],
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

    private function exampleErrorCode(int $status, string $route): string
    {
        return match (true) {
            401 === $status && 'POST /api/login_check' === $route => 'auth.invalid_credentials',
            401 === $status => 'auth.token_missing',
            422 === $status => 'validation.failed',
            default => ProblemResponse::httpErrorCode($status),
        };
    }

    /**
     * Adds the properties to a plain object schema, or to the object part of a JSON-LD "allOf" schema.
     *
     * @param array<mixed> $schema
     *
     * @return array<mixed>
     */
    private function withErrorCode(array $schema): array
    {
        if (is_array($schema['properties'] ?? null)) {
            $schema['properties'] = $this->properties($schema['properties']);

            return $this->withRequiredErrorCode($schema);
        }

        // A schema with neither properties nor allOf is left as it is (an empty allOf is not valid OpenAPI).
        if (!is_array($schema['allOf'] ?? null)) {
            return $schema;
        }

        $parts = $schema['allOf'];
        foreach ($parts as $index => $part) {
            $part = $this->toArray($part);
            if (is_array($part) && is_array($part['properties'] ?? null)) {
                $part['properties'] = $this->properties($part['properties']);
                $parts[$index] = $this->withRequiredErrorCode($part);
            }
        }
        $schema['allOf'] = $parts;

        return $schema;
    }

    /**
     * "errorCode" and "params" are on every error, so generated clients must not treat them as optional.
     *
     * @param array<mixed> $schema
     *
     * @return array<mixed>
     */
    private function withRequiredErrorCode(array $schema): array
    {
        $required = is_array($schema['required'] ?? null) ? array_filter($schema['required'], 'is_string') : [];
        $schema['required'] = array_values(array_unique([...$required, 'errorCode', 'params']));

        return $schema;
    }

    /**
     * @param array<mixed> $properties
     *
     * @return array<mixed>
     */
    private function properties(array $properties): array
    {
        // API Platform documents it, but the responses never contain it.
        unset($properties['instance']);

        $properties['type'] = [
            'type' => 'string',
            'readOnly' => true,
            'description' => 'Problem type: "'.ProblemResponse::TYPE_PREFIX.'" followed by errorCode.',
        ];
        $properties['title'] = [
            'type' => 'string',
            'readOnly' => true,
            'description' => 'Name of the HTTP status, e.g. "Unprocessable Content".',
        ];
        // API Platform's own example (404) would show up on every error response.
        $properties['status'] = [
            'type' => 'integer',
            'readOnly' => true,
            'description' => 'HTTP status code of the response.',
        ];
        $properties['errorCode'] = [
            'type' => 'string',
            'readOnly' => true,
            // No examples here: Swagger UI would show them on every response instead of each response's own one.
            'description' => 'Stable code of the problem, e.g. "transfer.accounts_must_differ", "validation.failed" '
                .'or "http.not_found".',
        ];
        $properties['params'] = $this->paramsSchema('Values for the message, e.g. {"fromCurrency": "PLN"}.');

        $violations = $this->toArray($properties['violations'] ?? null);
        $items = $this->toArray(is_array($violations) ? ($violations['items'] ?? null) : null);
        if (is_array($violations) && is_array($items) && is_array($items['properties'] ?? null)) {
            $items['properties']['code'] = [
                'type' => 'string',
                'description' => 'Stable code of the violation, e.g. "transfer.to_account_required" or '
                    .'"validation.invalid_type".',
            ];
            $items['properties']['params'] = $this->paramsSchema(
                'Values for the message: only "limit", "min", "max", "type"; never the submitted value.',
            );
            // API Platform documents them, but the responses never contain them.
            unset($items['properties']['hint'], $items['properties']['payload']);
            $items['required'] = ['propertyPath', 'message', 'code', 'params'];
            $violations['items'] = $items;
            $properties['violations'] = $violations;
        }

        return $properties;
    }

    /**
     * API Platform keeps some schema parts as ArrayObject instead of arrays.
     */
    private function toArray(mixed $value): mixed
    {
        return $value instanceof \ArrayObject ? $value->getArrayCopy() : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function paramsSchema(string $description): array
    {
        return [
            'type' => 'object',
            'readOnly' => true,
            'additionalProperties' => ['type' => 'string'],
            'description' => $description,
        ];
    }
}
