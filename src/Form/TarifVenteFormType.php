<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Clients;
use App\Entity\Devises;
use App\Entity\Dossier;
use App\Entity\Tarifs;
use App\Entity\Tarifvente;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TarifVenteFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateeffet', DateType::class, [
                'widget' => 'single_text',
                // this is actually the default format for single_text                
                //'format' => 'dd-MM-yyyy  HH:mm',
                'data'   => new \DateTime(),
                'required' => true
            ])
            ->add('prix')
            ->add('dossier', EntityType::class, [
                'class' => Dossier::class,
                'choice_label' => 'nom',
            ])
            ->add('tarif', EntityType::class, [
                'class' => Tarifs::class,
                'choice_label' => 'libelle',
            ])
            ->add('article', EntityType::class, [
                'class' => Article::class,
                'choice_label' => 'libelle',
            ])
            ->add('client', EntityType::class, [
                'class' => Clients::class,
                'choice_label' => 'nom',
            ])
            ->add('devise', EntityType::class, [
                'class' => Devises::class,
                'choice_label' => 'libelle',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tarifvente::class,
        ]);
    }
}
