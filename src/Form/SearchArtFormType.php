<?php

namespace App\Form;

use App\Model\SearchData;
use App\Model\SearchDataArt;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchArtFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'attr' => [
                    'placeholder' => 'Recherche par libellé...'
                ],
                'empty_data' => '',
                'required' => false
            ]);
            
        
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SearchDataArt::class,
            'method' => 'GET',
            'csrf_protection' => false
        ]);
    }
}
