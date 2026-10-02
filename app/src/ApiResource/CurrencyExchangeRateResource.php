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
use App\Entity\CurrencyExchangeRate;
use App\State\CurrencyExchangeRateHistoryProvider;
use App\State\CurrencyExchangeRateProcessor;
use App\State\CurrencyExchangeRateProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'CurrencyExchangeRate',
    description: 'Exchange rate of a currency to PLN for a given bank and date. Mirrors the "Kursy" / '
    .'"Dodaj kurs" form.',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Lists the most recent exchange rates (fixed limit, no pagination), like the web list.',
            provider: CurrencyExchangeRateProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/currency_exchange_rates/history',
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Full historical rates for the tracked currency, all banks and dates, no limit or '
            .'pagination. Intended for charting; the client groups/dedups as needed.',
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
            .'allowed; the most recently added one (highest id) is treated as the current rate.',
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

    #[ApiProperty(description: 'Rate date, format YYYY-MM-DD. Required.', example: '2026-09-29')]
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
}
