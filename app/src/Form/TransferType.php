<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Account;
use App\Repository\AccountRepository;
use App\Util\BusinessDateRange;
use App\Validator\BusinessDate;
use App\Validator\ValidTransfer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;

class TransferType extends AbstractType
{
    public function __construct(private readonly AccountRepository $accountRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Choices are loaded up front, so a submitted ID is compared in PHP and never reaches SQL.
        // Hidden accounts are not offered on either side of a transfer.
        $accounts = $this->accountRepository->findVisibleOrderedForSelect();
        // Lets the page block the second amount when both accounts use one currency.
        $currencyAttribute = static fn (Account $account): array => [
            'data-currency' => $account->getCurrency()->getCode(),
        ];

        $builder
            ->add('fromAccount', EntityType::class, [
                'class' => Account::class,
                'choice_label' => 'displayLabel',
                'choices' => $accounts,
                'choice_attr' => $currencyAttribute,
                'constraints' => [new NotNull(message: 'transfer.from_account_required')],
            ])
            ->add('toAccount', EntityType::class, [
                'class' => Account::class,
                'choice_label' => 'displayLabel',
                'choices' => $accounts,
                'choice_attr' => $currencyAttribute,
                'constraints' => [new NotNull(message: 'transfer.to_account_required')],
            ])
            ->add('amountFrom', MoneyType::class, [
                'currency' => false,
                'grouping' => true,
                'input' => 'string',
                'constraints' => [
                    new NotBlank(message: 'transfer.amount_from_required'),
                    new Positive(message: 'transfer.amount_must_be_positive'),
                    new LessThanOrEqual(value: 999999.99, message: 'transfer.amount_out_of_range'),
                ],
            ])
            ->add('amountTo', MoneyType::class, [
                'currency' => false,
                'grouping' => true,
                'input' => 'string',
                'required' => false,
                'constraints' => [
                    new Positive(message: 'transfer.amount_must_be_positive'),
                    new LessThanOrEqual(value: 999999.99, message: 'transfer.amount_out_of_range'),
                ],
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'data' => new \DateTimeImmutable(),
                'input' => 'datetime_immutable',
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'common.date_not_blank'),
                    new BusinessDate(),
                ],
                'attr' => [
                    'min' => BusinessDateRange::MIN_DATE,
                    'max' => BusinessDateRange::MAX_DATE,
                ],
            ])
            ->add('description', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255, maxMessage: 'common.description_max_length')],
            ]);

        // With one currency the second amount plays no role, so whatever was typed into it is not even read.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($accounts): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $currencies = [];
            foreach ($accounts as $account) {
                $currencies[(string) $account->getId()] = $account->getCurrency()->getCode();
            }

            $from = is_string($data['fromAccount'] ?? null) ? $currencies[$data['fromAccount']] ?? null : null;
            $to = is_string($data['toAccount'] ?? null) ? $currencies[$data['toAccount']] ?? null : null;
            if (null !== $from && $from === $to) {
                unset($data['amountTo']);
                $event->setData($data);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'constraints' => [
                new ValidTransfer(),
            ],
        ]);
    }
}
