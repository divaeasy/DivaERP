<?php

namespace App\Form;

use App\Entity\Devises;
use App\Entity\Dossier;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DossierFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('adresse')
            ->add('logoFile', FileType::class, [
                'label' => 'Logo',
                'mapped' => false,
                'required' => false,
                'help' => 'PNG ou JPG, 1 Mo max.',
            ])
            ->add('rc')
            ->add('theme', ChoiceType::class, [
                'label' => false,
                'required' => true,
                'expanded' => true,
                'multiple' => false,
                'empty_data' => 'neutral',
                'choices' => [
                    'Ocean' => 'ocean',
                    'Forest' => 'forest',
                    'Sand' => 'sand',
                    'Night' => 'night',
                    'Neutral' => 'neutral',
                ],
                'choice_attr' => static function ($choice, string $label, string $value): array {
                    return [
                        'class' => 'theme-radio-input',
                        'data-theme' => $value,
                    ];
                },
            ])
            ->add('devise', EntityType::class, [
                'class' => Devises::class,
                'choice_label' => 'code',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dossier::class,
        ]);
    }
}
