<?php

namespace App\Form;

use App\Model\SearchData;
use App\Model\SearchDataCli;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'attr' => [
                    'placeholder' => 'Rechercher par nom...'
                ],
                'empty_data' => '',
                'required' => false
            ])
            ->add('tel', TextType::class, [
                'attr' => [
                    'placeholder' => 'Rechercher par téléphone...'
                ],
                'empty_data' => '',
                'required' => false
            ]);
        /*$builder
            ->add('nom')
            ->add('adr1')
            ->add('adr2')
            ->add('rue')
            ->add('codepostal')
            ->add('tel')
            ->add('email')
            ->add('web')
            ->add('linkedin')
            ->add('dossier', EntityType::class, [
                'class' => Dossier::class,
                'choice_label' => 'id',
            ])
            ->add('ville', EntityType::class, [
                'class' => Ville::class,
                'choice_label' => 'id',
            ])
            ->add('pays', EntityType::class, [
                'class' => Pays::class,
                'choice_label' => 'id',
            ])
            ->add('tarif', EntityType::class, [
                'class' => Tarifs::class,
                'choice_label' => 'id',
            ])
            ->add('reglement', EntityType::class, [
                'class' => Reglement::class,
                'choice_label' => 'id',
            ])
        ;*/
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SearchData::class,
            'method' => 'GET',
            'csrf_protection' => false
        ]);
    }
}
