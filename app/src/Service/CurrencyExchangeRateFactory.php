<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\Currencies;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Form\FormData;
use App\ValueObject\Money;
use Symfony\Component\Form\FormInterface;

final readonly class CurrencyExchangeRateFactory
{
    public function createFromForm(FormInterface $form): CurrencyExchangeRate
    {
        $rate = Money::fromDecimalString(
            FormData::decimal($form, 'rateToPln'),
            Currencies::SETTLEMENT_CURRENCY_CODE,
            Currencies::EXCHANGE_RATE_SCALE,
        );

        return new CurrencyExchangeRate(
            FormData::entity($form, 'bank', Bank::class),
            FormData::entity($form, 'currency', Currency::class),
            FormData::date($form, 'rateDate'),
            $rate->getMinorUnits(),
        );
    }
}
