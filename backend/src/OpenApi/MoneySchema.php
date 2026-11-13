<?php

declare(strict_types=1);

namespace App\OpenApi;

/**
 * The OpenAPI schema of an amount of money as the API writes it (Money::toArray()): {"amount": "4935.00", "currency":
 * "PLN"}, both always there. For the #[ApiProperty(schema: ...)] of a field that holds one: API Platform takes such a
 * schema whole, so the field adds its own description and example to it.
 */
final class MoneySchema
{
    public const array OBJECT = [
        'type' => 'object',
        'required' => ['amount', 'currency'],
        'properties' => [
            'amount' => ['type' => 'string', 'description' => 'A decimal string.', 'example' => '4935.00'],
            'currency' => ['type' => 'string', 'description' => 'Currency code.', 'example' => 'PLN'],
        ],
    ];

    /**
     * @codeCoverageIgnore The class has only static members: no instance is ever made, so this never runs.
     */
    private function __construct()
    {
    }
}
