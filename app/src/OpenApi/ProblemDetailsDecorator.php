<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
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
        `params` (only `limit`, `min`, `max`; never the submitted value) and an English `message`.

        Business errors:

        | errorCode | Status | params |
        | --- | --- | --- |
        | `transfer.accounts_must_differ` | 422 | |
        | `transfer.amount_to_required_for_currency_exchange` | 422 | `fromCurrency`, `toCurrency` |
        | `fixed_expense.plan_no_main_account` | 409 | |
        | `fixed_expense.plan_main_not_own` | 409 | `account`, `household` |
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

        $info = $openApi->getInfo();

        return $openApi->withInfo($info->withDescription($info->getDescription().self::ERRORS_DESCRIPTION));
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
        $properties['errorCode'] = [
            'type' => 'string',
            'readOnly' => true,
            'description' => 'Stable code of the problem; see "Errors" in the API description.',
            'examples' => ['transfer.accounts_must_differ', 'validation.failed', 'http.not_found'],
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
