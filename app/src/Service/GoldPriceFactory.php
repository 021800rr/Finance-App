<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\Currencies;
use App\Entity\GoldAsset;
use App\Entity\GoldPrice;
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
        $plnCurrency = $this->currencyRepository->findPlnOrThrow();

        $goldAsset = $form->get('goldAsset')->getData();
        $priceDate = $form->get('priceDate')->getData();
        $rawPrice = $form->get('pricePln')->getData();
        if (!$goldAsset instanceof GoldAsset || !$priceDate instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException('Form data is incomplete or has unexpected types.');
        }

        if (!is_string($rawPrice)) {
            throw new \InvalidArgumentException('Price must be a numeric value.');
        }

        $priceMoney = Money::fromDecimalString($rawPrice, Currencies::SETTLEMENT_CURRENCY_CODE);

        return new GoldPrice(
            goldAsset: $goldAsset,
            priceDate: $priceDate,
            pricePln: $priceMoney->getMinorUnits(),
            currency: $plnCurrency,
        );
    }
}
