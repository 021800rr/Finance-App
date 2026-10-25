<?php

declare(strict_types=1);

namespace App\Form;

use App\Constant\Currencies;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Util\BusinessDateRange;
use App\Validator\BusinessDate;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class CurrencyExchangeRateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('bank', EntityType::class, [
                'class' => Bank::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $bankRepository) {
                    return $bankRepository->createQueryBuilder('b')
                        ->join('b.accounts', 'a')
                        ->join('a.currency', 'c')
                        ->where('a.hidden = false')
                        ->andWhere('c.code != :currency')
                        ->setParameter('currency', Currencies::SETTLEMENT_CURRENCY_CODE)
                        ->distinct()
                        ->orderBy('b.name', 'ASC');
                },
            ])
            ->add('currency', EntityType::class, [
                'class' => Currency::class,
                'choice_label' => 'code',
                'query_builder' => function (EntityRepository $currencyRepository) {
                    return $currencyRepository->createQueryBuilder('c')
                        ->where('c.code != :code')
                        ->setParameter('code', Currencies::SETTLEMENT_CURRENCY_CODE)
                        ->orderBy('c.code', 'ASC');
                },
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
                    new Range(
                        notInRangeMessage: 'currency_exchange_rate.rate_out_of_range',
                        min: 0.0001,
                        max: 100,
                    ),
                ],
            ]);
    }
}
