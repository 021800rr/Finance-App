<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\Currencies;
use App\Entity\Contractor;
use App\Entity\FixedExpense;
use App\Form\FormData;
use App\Repository\CurrencyRepository;
use App\ValueObject\Money;
use Symfony\Component\Form\FormInterface;

final readonly class FixedExpenseFactory
{
    public function __construct(private CurrencyRepository $currencyRepository)
    {
    }

    public function createFromForm(FormInterface $form, Contractor $contractor): FixedExpense
    {
        $fixedExpense = new FixedExpense(
            $contractor,
            FormData::integer($form, 'day'),
            $this->amount($form),
            $this->currencyRepository->findPlnOrThrow()
        );
        $fixedExpense->setDescription(FormData::optionalText($form, 'description'));

        return $fixedExpense;
    }

    public function updateFromForm(FixedExpense $fixedExpense, FormInterface $form, Contractor $contractor): void
    {
        $fixedExpense->setContractor($contractor);
        $fixedExpense->setDay(FormData::integer($form, 'day'));
        $fixedExpense->setAmount($this->amount($form));
        $fixedExpense->setDescription(FormData::optionalText($form, 'description'));
        $fixedExpense->setCurrency($this->currencyRepository->findPlnOrThrow());
    }

    private function amount(FormInterface $form): int
    {
        return Money::fromDecimalString(FormData::decimal($form, 'amount'), Currencies::SETTLEMENT_CURRENCY_CODE)
            ->getMinorUnits();
    }
}
