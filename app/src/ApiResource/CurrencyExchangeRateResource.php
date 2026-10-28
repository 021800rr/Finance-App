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
use App\Constant\Currencies;
use App\Entity\CurrencyExchangeRate;
use App\State\CurrencyExchangeRateHistoryProvider;
use App\State\CurrencyExchangeRateProcessor;
use App\State\CurrencyExchangeRateProvider;
use App\Util\BusinessDateRange;
use App\ValueObject\Money;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'CurrencyExchangeRate',
    description: 'Exchange rate of a currency to PLN for a given bank and date.',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(
                summary: 'Lists the 20 most recent exchange rates.',
                description: 'Returns 20 entries ordered by date then id descending, '
                .'with no pagination, like the web list.',
                security: [['JWT' => []]],
            ),
            paginationEnabled: false,
            provider: CurrencyExchangeRateProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/currency_exchange_rates/history',
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
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Adds a new exchange rate. Multiple rates for the same bank, currency and date are '
            .'allowed; the most recent date (and, within a date, highest id) is treated as the current rate.',
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
final class CurrencyExchangeRateResource
{
    #[ApiProperty(description: 'Rate id. Read-only, ignored on write.', writable: false, example: 1)]
    public ?int $id = null;

    #[ApiProperty(description: 'Id of the bank this rate applies to. Required.', example: 1)]
    #[Assert\NotNull(message: 'currency_exchange_rate.bank_required')]
    public ?int $bankId = null;

    #[ApiProperty(description: 'Currency code, e.g. "CHF". Required.', example: 'CHF')]
    #[Assert\NotBlank(message: 'currency_exchange_rate.currency_required')]
    public ?string $currencyCode = null;

    #[ApiProperty(description: 'Rate date, format YYYY-MM-DD, from '.BusinessDateRange::MIN_DATE.' to '
        .BusinessDateRange::MAX_DATE.' inclusive. Required.', example: '2026-09-29')]
    #[Assert\NotBlank(message: 'common.date_not_blank')]
    public ?string $rateDate = null;

    #[ApiProperty(
        description: 'Exchange rate to PLN as a decimal string, e.g. "4.3210". Range 0.0001-100. Required.',
        example: '4.3210',
    )]
    #[Assert\NotBlank(message: 'currency_exchange_rate.rate_not_blank')]
    public ?string $rateToPln = null;

    public static function fromEntity(CurrencyExchangeRate $rate): self
    {
        $resource = new self();
        $resource->id = $rate->getId();
        $resource->bankId = $rate->getBank()->getId();
        $resource->currencyCode = $rate->getCurrency()->getCode();
        $resource->rateDate = $rate->getRateDate()->format('Y-m-d');
        $resource->rateToPln = $rate->getRateToPlnAsMoney()->toDecimalString();

        return $resource;
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
        $resource = new self();
        $resource->id = (int) $row['id'];
        $resource->bankId = (int) $row['bank_id'];
        $resource->currencyCode = $row['currency_code'];
        $resource->rateDate = $row['rate_date'];
        $resource->rateToPln = Money::fromMinorUnits(
            (int) $row['rate_to_pln_minor_units'],
            Currencies::SETTLEMENT_CURRENCY_CODE,
            Currencies::EXCHANGE_RATE_SCALE,
        )->toDecimalString();

        return $resource;
    }
}
