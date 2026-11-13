<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Repository\AccountRepository;
use App\Util\BusinessDateRange;
use App\Validator\BusinessDate;
use App\ValueObject\Money;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;

class AccountOperationType extends AbstractType
{
    public function __construct(private readonly AccountRepository $accountRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ?AccountOperation $existingOperation */
        $existingOperation = $options['existingOperation'];
        /** @var ?Account $preselectedAccount */
        $preselectedAccount = $options['preselectedAccount'];

        $builder
            ->add('account', EntityType::class, [
                'class' => Account::class,
                'choice_label' => 'displayLabel',
                // Choices are loaded up front, so a submitted ID is compared in PHP and never reaches SQL.
                // Editing shows only the operation's own account, which stays fixed even if hidden later.
                'choices' => null !== $existingOperation
                    ? [$existingOperation->getAccount()]
                    : $this->accountRepository->findVisibleOrderedForSelect(),
                'constraints' => [new NotNull(message: 'operation.account_required')],
                'data' => $preselectedAccount ?? $existingOperation?->getAccount(),
                // An operation belongs to its account for good: the account is chosen only when it is created.
                'disabled' => null !== $existingOperation,
            ])
            ->add('contractor', HiddenType::class, [
                'mapped' => false,
                'required' => true,
            ])
            ->add('operationDate', DateType::class, [
                'widget' => 'single_text',
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
                'data' => $existingOperation?->getOperationDate() ?? new \DateTimeImmutable(),
            ])
            ->add('amount', MoneyType::class, [
                'currency' => false,
                'grouping' => true,
                'input' => 'string',
                'required' => true,
                'invalid_message' => 'common.numeric_invalid',
                'constraints' => [
                    new NotBlank(message: 'common.amount_not_blank'),
                    new NotEqualTo(value: 0, message: 'common.amount_cannot_be_zero'),
                    new Range(
                        notInRangeMessage: 'common.amount_out_of_range',
                        min: -999999.99,
                        max: 999999.99,
                    ),
                ],
                'data' => $existingOperation ? $this->moneyDecimal($existingOperation) : null,
            ])
            ->add('description', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255, maxMessage: 'common.description_max_length')],
                'data' => $existingOperation?->getDescription(),
            ])
            ->add('confirmed', CheckboxType::class, [
                'required' => false,
                'data' => $existingOperation?->isConfirmed() ?? false,
            ]);
    }

    private function moneyDecimal(AccountOperation $operation): string
    {
        return Money::fromMinorUnits($operation->getAmount(), $operation->getCurrency()->getCode())->toDecimalString();
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'preselectedAccount' => null,
            'existingOperation' => null,
        ]);
        $resolver->setAllowedTypes('preselectedAccount', ['null', Account::class]);
        $resolver->setAllowedTypes('existingOperation', ['null', AccountOperation::class]);
    }
}
