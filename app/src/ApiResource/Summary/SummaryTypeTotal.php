<?php

declare(strict_types=1);

namespace App\ApiResource\Summary;

use ApiPlatform\Metadata\ApiProperty;
use App\OpenApi\MoneySchema;
use App\ValueObject\Money;

/**
 * The own-funds accounts of one type: their balance in each currency, and the sum of them in PLN.
 */
final readonly class SummaryTypeTotal
{
    private const string RAW_DESCRIPTION = 'The balance in each currency, by currency code.';
    private const array RAW_EXAMPLE = [
        'CHF' => ['amount' => '1629.00', 'currency' => 'CHF'],
        'PLN' => ['amount' => '1625.04', 'currency' => 'PLN'],
    ];
    private const string PLN_DESCRIPTION = 'The sum in PLN; an account whose bank has no rate to PLN is left out.';
    private const array PLN_EXAMPLE = ['amount' => '9051.65', 'currency' => 'PLN'];

    /**
     * @param array<string, array{amount: string, currency: string}> $raw
     * @param array{amount: string, currency: string}                $pln
     */
    public function __construct(
        #[ApiProperty(
            description: self::RAW_DESCRIPTION,
            required: true,
            example: self::RAW_EXAMPLE,
            schema: [
                'type' => 'object',
                'description' => self::RAW_DESCRIPTION,
                'example' => self::RAW_EXAMPLE,
                'additionalProperties' => MoneySchema::OBJECT,
            ],
        )]
        public array $raw,
        #[ApiProperty(
            description: self::PLN_DESCRIPTION,
            required: true,
            example: self::PLN_EXAMPLE,
            schema: [
                ...MoneySchema::OBJECT,
                'description' => self::PLN_DESCRIPTION,
                'example' => self::PLN_EXAMPLE,
            ],
        )]
        public array $pln,
    ) {
    }

    /**
     * @param array{raw: array<string, Money>, pln: Money} $total
     */
    public static function fromTotal(array $total): self
    {
        return new self(
            raw: array_map(static fn (Money $money): array => $money->toArray(), $total['raw']),
            pln: $total['pln']->toArray(),
        );
    }
}
