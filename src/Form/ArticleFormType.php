<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Dossier;
use App\Entity\Tarifs;
use App\Entity\Unite;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArticleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle')
            ->add('dossier', EntityType::class, [
                'class' => Dossier::class,
                'choice_label' => 'nom'
            ])
            ->add('unite', EntityType::class, [
                'class' => Unite::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Sélectionner une unité',
            ])
            ->add('tarif', EntityType::class, [
                'class' => Tarifs::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Sélectionner un tarif',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}
