<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\GoldAsset;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Range;

class GoldPriceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('goldAsset', EntityType::class, [
                'class' => GoldAsset::class,
                'choice_label' => fn (GoldAsset $asset) => $asset->getDescription(),
                'query_builder' => function (EntityRepository $assetRepository) {
                    return $assetRepository->createQueryBuilder('ga')
                        ->orderBy('ga.id', 'ASC');
                },
            ])
            ->add('priceDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => true,
                'data' => new \DateTimeImmutable(),
            ])
            ->add('pricePln', MoneyType::class, [
                'currency' => false,
                'grouping' => true,
                'input' => 'string',
                'required' => true,
                'invalid_message' => 'common.numeric_invalid',
                'constraints' => [
                    new Range(
                        notInRangeMessage: 'gold_price.price_out_of_range',
                        min: 0.01,
                        max: 1000000,
                    ),
                ],
            ]);
    }
}
