<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contractor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ContractorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'empty_data' => '',
            'constraints' => [new NotBlank(message: 'contractor.name_not_blank')],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contractor::class,
            'empty_data' => static function (FormInterface $form): ?Contractor {
                $name = $form->get('name')->getData();

                // Let the field report an empty name before constructing an entity that requires one.
                return is_string($name) && '' !== $name ? new Contractor($name) : null;
            },
        ]);
    }
}
