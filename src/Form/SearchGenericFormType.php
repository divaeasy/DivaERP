<?php

namespace App\Form;

use App\Model\SearchGeneric;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchGenericFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('q', TextType::class, [
                'attr' => [
                    'placeholder' => $options['placeholder'] ?? 'Rechercher...'
                ],
                'empty_data' => '',
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SearchGeneric::class,
            'method' => 'GET',
            'csrf_protection' => false,
            'placeholder' => 'Rechercher...',
        ]);
    }
}
