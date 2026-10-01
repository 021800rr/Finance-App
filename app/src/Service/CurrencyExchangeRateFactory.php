<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\Currencies;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\ValueObject\Money;
use Symfony\Component\Form\FormInterface;

final readonly class CurrencyExchangeRateFactory
{
    public function createFromForm(FormInterface $form): CurrencyExchangeRate
    {
        $bank = $form->get('bank')->getData();
        $currency = $form->get('currency')->getData();
        $rateDate = $form->get('rateDate')->getData();
        $rawRate = $form->get('rateToPln')->getData();

        if (!$bank instanceof Bank || !$currency instanceof Currency || !$rateDate instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException('Form data is incomplete or has unexpected types.');
        }

        if (!is_string($rawRate)) {
            throw new \InvalidArgumentException('Rate must be a numeric value.');
        }

        $rateMoney = Money::fromDecimalString($rawRate, Currencies::SETTLEMENT_CURRENCY_CODE, Currencies::EXCHANGE_RATE_SCALE);

        return new CurrencyExchangeRate($bank, $currency, $rateDate, $rateMoney->getMinorUnits());
    }
}
