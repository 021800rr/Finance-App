<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\ValueObject\Quantity;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

class GoldAssetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ?GoldAsset $existingAsset */
        $existingAsset = $options['existingAsset'];

        $builder
            ->add('form', EntityType::class, [
                'class' => GoldForm::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $formRepository) {
                    return $formRepository->createQueryBuilder('gf')
                        ->orderBy('gf.id', 'ASC');
                },
                'data' => $existingAsset?->getForm(),
            ])
            ->add('ounces', NumberType::class, [
                'required' => true,
                'input' => 'string',
                'invalid_message' => 'common.numeric_invalid',
                'constraints' => [
                    new Range(
                        notInRangeMessage: 'gold_asset.ounces_out_of_range',
                        min: 0.0001,
                        max: 1000,
                    ),
                ],
                'data' => $existingAsset ? $this->ouncesDecimal($existingAsset) : null,
            ])
            ->add('description', TextType::class, [
                'required' => false,
                'data' => $existingAsset?->getDescription(),
            ]);
    }

    private function ouncesDecimal(GoldAsset $asset): string
    {
        return Quantity::fromMinorUnits($asset->getOunces())->toDecimalString();
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'existingAsset' => null,
        ]);
        $resolver->setAllowedTypes('existingAsset', ['null', GoldAsset::class]);
    }
}
