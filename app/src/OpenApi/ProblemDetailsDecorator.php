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
 * Documents the error contract added by ProblemNormalizer and ViolationCodes: "errorCode" and "params"
 * on every error, a stable "code" and "params" on every violation, and the list of business error codes.
 */
final readonly class ProblemDetailsDecorator implements OpenApiFactoryInterface
{
    private const array ERROR_SCHEMAS = ['Error', 'Error.jsonld', 'ConstraintViolation', 'ConstraintViolation.jsonld'];

    private const string ERRORS_DESCRIPTION = <<<'MD'


        ## Errors

        Every error is [RFC 9457 Problem Details](https://www.rfc-editor.org/rfc/rfc9457.html)
        (`Content-Type: application/problem+json`). Build your own message from `errorCode` and `params`;
        `detail` is a plain English sentence for curl/Postman and may change wording at any time.

        - `errorCode`: stable code of the problem; `type` is `urn:finance-app:problem:` followed by it.
        - `params`: values for the message, always an object (`{}` when there are none), values are strings.
        - `detail`: English explanation. For an unexpected server error it is generic and reveals nothing.

        Validation errors (422) have `errorCode` `validation.failed` and a `violations` list. Each violation has
        `propertyPath` (the JSON field), `code` (e.g. `transfer.to_account_required`; a constraint without its own
        code gives `validation.` plus the Symfony error name, e.g. `validation.invalid_type`, `validation.too_short`,
        and one whose error cannot be named gives `validation.invalid`),
        `params` (only `limit`, `min`, `max`, `type`; never the submitted value) and an English `message`.

        Business errors:

        | errorCode | Status | params |
        | --- | --- | --- |
        | `contractor.name_not_unique` | 409 | |
        | `account.not_found` | 404 | |
        | `account_operation.not_found` | 404 | |
        | `account_type.not_found` | 404 | |
        | `bank.not_found` | 404 | |
        | `contractor.not_found` | 404 | |
        | `currency.not_found` | 404 | |
        | `currency_exchange_rate.not_found` | 404 | |
        | `fixed_expense.not_found` | 404 | |
        | `gold_asset.not_found` | 404 | |
        | `gold_form.not_found` | 404 | |
        | `gold_price.not_found` | 404 | |
        | `household.not_found` | 404 | |
        | `transfer.accounts_must_differ` | 422 | |
        | `transfer.amount_to_required_for_currency_exchange` | 422 | `fromCurrency`, `toCurrency` |
        | `fixed_expense.plan_no_main_account` | 409 | |
        | `fixed_expense.plan_main_not_own` | 409 | `account`, `household` |
        | `fixed_expense.plan_main_hidden` | 409 | `account` |
        | `fixed_expense.plan_currency_mismatch` | 409 | `expenseCurrency`, `account`, `accountCurrency` |
        | `fixed_expense.plan_duplicate` | 409 | `contractor`, `amount`, `date` |

        Authentication errors (status 401, sent with `WWW-Authenticate: Bearer`):

        | errorCode | Meaning | params |
        | --- | --- | --- |
        | `auth.token_missing` | No `Authorization: Bearer` token | |
        | `auth.token_invalid` | The token cannot be used: malformed, wrong signature, or its user no longer exists | |
        | `auth.token_expired` | The token has expired; log in again | |
        | `auth.invalid_credentials` | Wrong email or password at `POST /api/login_check` | |
        | `auth.too_many_login_attempts` | Login locked after too many failed attempts; also `Retry-After` | `minutes` |
        | `auth.login_failed` | Login refused for another reason | |

        Any other error has `errorCode` `http.` followed by a fixed name of the HTTP status, e.g. `http.not_found`,
        `http.bad_request`, `http.unprocessable_content`, `http.internal_server_error`, with empty `params`
        (a status without a fixed name gets its number, e.g. `http.418`). Unlike `title`, these codes do not follow
        wording changes of the status texts.
        MD;

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

        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            if ($pathItem instanceof PathItem) {
                $openApi->getPaths()->addPath($path, $this->withStatusExamples($pathItem));
            }
        }

        $info = $openApi->getInfo();

        return $openApi->withInfo($info->withDescription($info->getDescription().self::ERRORS_DESCRIPTION));
    }

    /**
     * All error responses share one schema, so Swagger UI would show the same example ("status": 404) for every
     * one of them. Each error response gets its own status and a matching example errorCode instead.
     */
    private function withStatusExamples(PathItem $pathItem): PathItem
    {
        return $pathItem
            ->withGet($this->withOperationExamples($pathItem->getGet()))
            ->withPost($this->withOperationExamples($pathItem->getPost()))
            ->withPut($this->withOperationExamples($pathItem->getPut()))
            ->withPatch($this->withOperationExamples($pathItem->getPatch()))
            ->withDelete($this->withOperationExamples($pathItem->getDelete()));
    }

    private function withOperationExamples(?Operation $operation): ?Operation
    {
        if (null === $operation || null === $operation->getResponses()) {
            return $operation;
        }

        $responses = [];
        foreach ($operation->getResponses() as $status => $response) {
            $responses[$status] = $response instanceof Response && (int) $status >= 400
                ? $this->withStatusExample($response, (int) $status)
                : $response;
        }

        return $operation->withResponses($responses);
    }

    private function withStatusExample(Response $response, int $status): Response
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
                    'errorCode' => ['type' => 'string', 'examples' => [$this->exampleErrorCode($status)]],
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

    private function exampleErrorCode(int $status): string
    {
        return 422 === $status ? 'validation.failed' : ProblemResponse::httpErrorCode($status);
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
                .'or "http.not_found"; see "Errors" in the API description.',
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
                'Values for the message: only "limit", "min", "max"; never the submitted value.',
            );
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
