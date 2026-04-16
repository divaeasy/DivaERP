<?php

namespace App\Form;

use App\Entity\NatureProduction;
use App\Enum\NatureProductionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class NatureProductionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', null, [
                'constraints' => [
                    new NotBlank(['message' => 'Le libelle est obligatoire.']),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => NatureProductionType::class,
                'choice_label' => static fn (NatureProductionType $choice): string => ucfirst($choice->value),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NatureProduction::class,
        ]);
    }
}
