<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Bank;
use App\Entity\Currency;
use App\Repository\BankRepository;
use App\Repository\CurrencyRepository;
use App\Util\BusinessDateRange;
use App\Validator\BusinessDate;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;

class CurrencyExchangeRateType extends AbstractType
{
    public function __construct(
        private readonly BankRepository $bankRepository,
        private readonly CurrencyRepository $currencyRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('bank', EntityType::class, [
                'class' => Bank::class,
                'choice_label' => 'name',
                // Choices are loaded up front, so a submitted ID is compared in PHP and never reaches SQL.
                'choices' => $this->bankRepository->findWithVisibleForeignCurrencyAccount(),
                'constraints' => [new NotNull(message: 'currency_exchange_rate.bank_required')],
            ])
            ->add('currency', EntityType::class, [
                'class' => Currency::class,
                'choice_label' => 'code',
                'choices' => $this->currencyRepository->findForeignOrderedByCode(),
                'constraints' => [new NotNull(message: 'currency_exchange_rate.currency_required')],
            ])
            ->add('rateDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => true,
                'constraints' => [new NotBlank(message: 'common.date_not_blank'), new BusinessDate()],
                'attr' => ['min' => BusinessDateRange::MIN_DATE, 'max' => BusinessDateRange::MAX_DATE],
                'data' => new \DateTimeImmutable(),
            ])
            ->add('rateToPln', MoneyType::class, [
                'currency' => false,
                'scale' => 4,
                'grouping' => true,
                'input' => 'string',
                'required' => true,
                'invalid_message' => 'common.numeric_invalid',
                'constraints' => [
                    new NotBlank(message: 'currency_exchange_rate.rate_not_blank'),
                    new Range(
                        notInRangeMessage: 'currency_exchange_rate.rate_out_of_range',
                        min: 0.0001,
                        max: 99.9999,
                    ),
                ],
            ]);
    }
}
