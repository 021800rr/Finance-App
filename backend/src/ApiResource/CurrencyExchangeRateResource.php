<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\ApiResource\Input\CurrencyExchangeRateInput;
use App\Constant\Currencies;
use App\Entity\CurrencyExchangeRate;
use App\State\CurrencyExchangeRateHistoryProvider;
use App\State\CurrencyExchangeRateProcessor;
use App\State\CurrencyExchangeRateProvider;
use App\ValueObject\Money;

/**
 * An exchange rate as the API returns it: every field is always there, and the OpenAPI document says so (a generated
 * client gets types without needless nulls). What a client sends is CurrencyExchangeRateInput (input: on POST), where
 * any field may be missing until the validation says why.
 */
#[ApiResource(
    shortName: 'CurrencyExchangeRate',
    description: 'Exchange rate of a currency to PLN for a given bank and date.',
    operations: [
        new GetCollection(
            uriTemplate: '/currency_exchange_rates/recent',
            openapi: new OpenApiOperation(
                summary: 'Retrieves the most recent currency exchange rates.',
                description: 'Returns the most recent entries ordered by date then id descending, with no pagination.',
                security: [['JWT' => []]],
            ),
            paginationEnabled: false,
            provider: CurrencyExchangeRateProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/currency_exchange_rates/chart',
            openapi: new OpenApiOperation(
                summary: 'Retrieves daily exchange rates for the chart.',
                description: 'Historical daily rates for the tracked currency, all banks and dates, no limit or '
                .'pagination. Returns the highest id per bank, currency and date, matching the web chart.',
                security: [['JWT' => []]]
            ),
            paginationEnabled: false,
            provider: CurrencyExchangeRateHistoryProvider::class,
        ),
        new Get(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Retrieves a single exchange rate by id.',
            provider: CurrencyExchangeRateProvider::class,
        ),
        new Post(
            openapi: new OpenApiOperation(
                responses: ['422' => new OpenApiResponse(
                    description: 'Validation failed, e.g. the bank has no visible foreign-currency account '
                    .'or currencyCode is PLN.',
                )],
                security: [['JWT' => []]],
            ),
            description: 'Adds a new exchange rate. Multiple rates for the same bank, currency and date are '
            .'allowed; the most recent date (and, within a date, highest id) is treated as the current rate. '
            .'As in the web form, the bank must have at least one visible (not hidden) account in a currency '
            .'other than PLN, and the currency must not be PLN; otherwise the request is rejected with 422.',
            input: CurrencyExchangeRateInput::class,
            processor: CurrencyExchangeRateProcessor::class,
        ),
        new Delete(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Deletes an exchange rate entry.',
            provider: CurrencyExchangeRateProvider::class,
            processor: CurrencyExchangeRateProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final readonly class CurrencyExchangeRateResource
{
    public function __construct(
        #[ApiProperty(description: 'Rate id.', required: true, example: 1)]
        public int $id,
        #[ApiProperty(description: 'Id of the bank this rate applies to.', required: true, example: 1)]
        public int $bankId,
        #[ApiProperty(description: 'Currency code, e.g. "CHF".', required: true, example: 'CHF')]
        public string $currencyCode,
        #[ApiProperty(description: 'Rate date, format YYYY-MM-DD.', required: true, example: '2026-09-29')]
        public string $rateDate,
        #[ApiProperty(
            description: 'Exchange rate to PLN as a decimal string with 4 decimal places, e.g. "4.3210".',
            required: true,
            example: '4.3210',
        )]
        public string $rateToPln,
    ) {
    }

    public static function fromEntity(CurrencyExchangeRate $rate): self
    {
        return new self(
            id: $rate->getId(),
            bankId: $rate->getBank()->getId(),
            currencyCode: $rate->getCurrency()->getCode(),
            rateDate: $rate->getRateDate()->format('Y-m-d'),
            rateToPln: $rate->getRateToPlnAsMoney()->toDecimalString(),
        );
    }

    /**
     * Build a resource from a database row with rate_date in YYYY-MM-DD format.
     *
     * @param array{
     *     id: int|string,
     *     bank_id: int|string,
     *     currency_code: string,
     *     rate_date: string,
     *     rate_to_pln_minor_units: int|string,
     * } $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            bankId: (int) $row['bank_id'],
            currencyCode: $row['currency_code'],
            rateDate: $row['rate_date'],
            rateToPln: Money::fromMinorUnits(
                (int) $row['rate_to_pln_minor_units'],
                Currencies::SETTLEMENT_CURRENCY_CODE,
                Currencies::EXCHANGE_RATE_SCALE,
            )->toDecimalString(),
        );
    }
}
