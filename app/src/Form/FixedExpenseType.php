<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Range;

class FixedExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('day', IntegerType::class, [
                'required' => true,
                'attr' => [
                    'min' => 1,
                    'max' => 28,
                ],
            ])
            ->add('contractor', HiddenType::class, [
                'mapped' => false,
                'required' => true,
            ])
            ->add('amount', MoneyType::class, [
                'currency' => false,
                'grouping' => true,
                'input' => 'string',
                'required' => true,
                'invalid_message' => 'common.numeric_invalid',
                'constraints' => [
                    new Range(
                        notInRangeMessage: 'fixed_expense.amount_must_be_negative',
                        min: -999999.99,
                        max: -0.01,
                    ),
                ],
            ])
            ->add('description', TextType::class, [
                'required' => false,
            ]);
    }
}
