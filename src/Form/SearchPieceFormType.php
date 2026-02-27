<?php

namespace App\Form;

use App\Model\SearchPiece;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchPieceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pieceref', TextType::class, [
                'attr' => [
                    'placeholder' => 'Rechercher par référence...'
                ],
                'empty_data' => '',
                'required' => false
            ])
            ->add('statut', TextType::class, [
                'attr' => [
                    'placeholder' => 'Rechercher par statut...'
                ],
                'empty_data' => '',
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SearchPiece::class,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
