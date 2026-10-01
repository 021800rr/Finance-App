<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\Currencies;
use App\Entity\Contractor;
use App\Entity\FixedExpense;
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
            $this->extractDay($form),
            $this->extractAmountMoney($form)->getMinorUnits(),
            $this->currencyRepository->findPlnOrThrow()
        );
        $fixedExpense->setDescription($this->extractDescription($form));

        return $fixedExpense;
    }

    public function updateFromForm(FixedExpense $fixedExpense, FormInterface $form, Contractor $contractor): void
    {
        $fixedExpense->setContractor($contractor);
        $fixedExpense->setDay($this->extractDay($form));
        $fixedExpense->setAmount($this->extractAmountMoney($form)->getMinorUnits());
        $fixedExpense->setDescription($this->extractDescription($form));
        $fixedExpense->setCurrency($this->currencyRepository->findPlnOrThrow());
    }

    private function extractDay(FormInterface $form): int
    {
        $day = $form->get('day')->getData();
        if (!is_int($day)) {
            throw new \InvalidArgumentException('Day must be an integer.');
        }

        return $day;
    }

    private function extractAmountMoney(FormInterface $form): Money
    {
        $rawAmount = $form->get('amount')->getData();
        if (!is_string($rawAmount)) {
            throw new \InvalidArgumentException('Amount must be a numeric value.');
        }

        return Money::fromDecimalString($rawAmount, Currencies::SETTLEMENT_CURRENCY_CODE);
    }

    private function extractDescription(FormInterface $form): ?string
    {
        $description = $form->get('description')->getData();

        return is_string($description) && '' !== trim($description) ? $description : null;
    }
}
