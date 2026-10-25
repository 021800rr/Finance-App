<?php

declare(strict_types=1);

namespace App\Form;

use App\Constant\Contractors;
use App\Entity\Account;
use App\Entity\Contractor;
use App\Repository\AccountRepository;
use App\Repository\ContractorRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TransferType extends AbstractType
{
    public function __construct(private readonly ContractorRepository $contractorRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fixed = $this->contractorRepository->findOrCreateByName(Contractors::SELF_TRANSFER_NAME);

        $builder
            ->add('fromAccount', EntityType::class, [
                'class' => Account::class,
                'choice_label' => 'displayLabel',
                'query_builder' => static fn (AccountRepository $accountRepository) => $accountRepository->createOrderedForSelectQueryBuilder(),
            ])
            ->add('toAccount', EntityType::class, [
                'class' => Account::class,
                'choice_label' => 'displayLabel',
                'query_builder' => static fn (AccountRepository $accountRepository) => $accountRepository->createOrderedForSelectQueryBuilder(),
            ])
            ->add('contractor', EntityType::class, [
                'class' => Contractor::class,
                'choice_label' => 'name',
                'data' => $fixed,
                'choice_attr' => static fn (Contractor $contractor) => $contractor->getId() === $fixed->getId() ? [] : ['disabled' => 'disabled'],
            ])
            ->add('amountFrom', MoneyType::class, [
                'currency' => false,
                'grouping' => true,
                'input' => 'string',
                'constraints' => [new Positive(message: 'transfer.amount_must_be_positive')],
            ])
            ->add('amountTo', MoneyType::class, [
                'currency' => false,
                'grouping' => true,
                'input' => 'string',
                'required' => false,
                'constraints' => [new Positive(message: 'transfer.amount_must_be_positive')],
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'data' => new \DateTimeImmutable(),
                'input' => 'datetime_immutable',
                'required' => true,
            ])
            ->add('description', TextType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'constraints' => [
                new Callback([self::class, 'validateAmountToRequiredForCurrencyExchange']),
            ],
        ]);
    }

    public static function validateAmountToRequiredForCurrencyExchange(mixed $data, ExecutionContextInterface $context): void
    {
        if (!is_array($data)) {
            return;
        }

        $fromAccount = $data['fromAccount'] ?? null;
        $toAccount = $data['toAccount'] ?? null;
        $amountTo = $data['amountTo'] ?? null;

        if (!$fromAccount instanceof Account || !$toAccount instanceof Account) {
            return;
        }

        if ($fromAccount->getCurrency()->getCode() !== $toAccount->getCurrency()->getCode() && null === $amountTo) {
            $context->buildViolation('transfer.amount_to_required_for_currency_exchange')
                ->atPath('[amountTo]')
                ->addViolation();
        }
    }
}
