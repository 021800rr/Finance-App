<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\GoldAsset;
use App\Repository\GoldAssetRepository;
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

class GoldPriceType extends AbstractType
{
    public function __construct(private readonly GoldAssetRepository $goldAssetRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('goldAsset', EntityType::class, [
                'class' => GoldAsset::class,
                'choice_label' => fn (GoldAsset $asset) => $asset->getDescription(),
                // Choices are loaded up front, so a submitted ID is compared in PHP and never reaches SQL.
                'choices' => $this->goldAssetRepository->findAllOrdered(),
                'constraints' => [new NotNull(message: 'gold_price.asset_not_null')],
            ])
            ->add('priceDate', DateType::class, [
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
                'data' => new \DateTimeImmutable(),
            ])
            ->add('pricePln', MoneyType::class, [
                'currency' => false,
                'grouping' => true,
                'input' => 'string',
                'required' => true,
                'invalid_message' => 'common.numeric_invalid',
                'constraints' => [
                    new NotBlank(message: 'gold_price.price_not_blank'),
                    new Range(
                        notInRangeMessage: 'gold_price.price_out_of_range',
                        min: 0.01,
                        max: 999999.99,
                    ),
                ],
            ]);
    }
}
