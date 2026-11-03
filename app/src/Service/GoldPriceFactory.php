<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\Currencies;
use App\Entity\GoldAsset;
use App\Entity\GoldPrice;
use App\Form\FormData;
use App\Repository\CurrencyRepository;
use App\ValueObject\Money;
use Symfony\Component\Form\FormInterface;

final readonly class GoldPriceFactory
{
    public function __construct(private CurrencyRepository $currencyRepository)
    {
    }

    public function createFromForm(FormInterface $form): GoldPrice
    {
        $price = Money::fromDecimalString(FormData::decimal($form, 'pricePln'), Currencies::SETTLEMENT_CURRENCY_CODE);

        return new GoldPrice(
            goldAsset: FormData::entity($form, 'goldAsset', GoldAsset::class),
            priceDate: FormData::date($form, 'priceDate'),
            pricePln: $price->getMinorUnits(),
            currency: $this->currencyRepository->findPlnOrThrow(),
        );
    }
}
