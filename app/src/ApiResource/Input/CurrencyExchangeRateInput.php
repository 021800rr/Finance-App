<?php

declare(strict_types=1);

namespace App\ApiResource\Input;

use ApiPlatform\Metadata\ApiProperty;
use App\Constant\Currencies;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Util\BusinessDateRange;
use App\Validator\BusinessDate;
use App\Validator\DecimalString;
use App\Validator\RateBank;
use App\Validator\RecordExists;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a client sends to add an exchange rate (POST). Any field may be missing or null here: the validation then says
 * which one and why, with a code the clients translate. What the API answers is CurrencyExchangeRateResource, where
 * every field is always there.
 */
final class CurrencyExchangeRateInput
{
    #[ApiProperty(
        description: 'Id of the bank this rate applies to. Required; the bank must have at least one visible '
        .'account in a currency other than PLN, otherwise it is rejected.',
        example: 1,
    )]
    #[Assert\NotNull(message: 'currency_exchange_rate.bank_required')]
    #[RecordExists(Bank::class, message: 'bank.not_found')]
    #[RateBank]
    public ?int $bankId = null;

    #[ApiProperty(
        description: 'Currency code, e.g. "CHF". Required; any existing currency except PLN.',
        example: 'CHF',
    )]
    #[Assert\NotBlank(message: 'currency_exchange_rate.currency_required')]
    #[RecordExists(Currency::class, message: 'currency.not_found')]
    // Same trim() as the processor uses to look the currency up, so " PLN " or "PLN\0" cannot slip through.
    #[Assert\Regex(
        pattern: '/^'.Currencies::SETTLEMENT_CURRENCY_CODE.'$/D',
        message: 'currency_exchange_rate.currency_pln_not_allowed',
        match: false,
        normalizer: 'trim',
    )]
    public ?string $currencyCode = null;

    #[ApiProperty(description: 'Rate date, format YYYY-MM-DD, from '.BusinessDateRange::MIN_DATE.' to '
        .BusinessDateRange::MAX_DATE.' inclusive. Required.', example: '2026-09-29')]
    #[Assert\NotBlank(message: 'common.date_not_blank')]
    #[BusinessDate]
    public ?string $rateDate = null;

    #[ApiProperty(
        description: 'Exchange rate to PLN as a decimal string, e.g. "4.3210". Range 0.0001-99.9999. Required.',
        example: '4.3210',
    )]
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'currency_exchange_rate.rate_not_blank', normalizer: 'trim'),
        new DecimalString(),
        new Assert\Range(
            min: 0.0001,
            max: 99.9999,
            notInRangeMessage: 'currency_exchange_rate.rate_out_of_range',
            invalidMessage: 'common.numeric_invalid',
        ),
    ])]
    public ?string $rateToPln = null;
}
