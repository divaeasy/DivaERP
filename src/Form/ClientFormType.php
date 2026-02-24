<?php

namespace App\Form;

use App\Entity\Clients;
use App\Entity\Dossier;
use App\Entity\Pays;
use App\Entity\Reglement;
use App\Entity\Tarifs;
use App\Entity\Ville;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('adr1')
            ->add('adr2', null, ['required' => false])
            ->add('rue', null, ['required' => false])
            ->add('codepostal', null, ['required' => false])
            ->add('tel', null, ['required' => false])
            ->add('email', null, ['required' => false])
            ->add('web', null, ['required' => false])
            ->add('linkedin', null, ['required' => false])
            ->add('dossier', EntityType::class, [
                'class' => Dossier::class,
                'choice_label' => 'nom',
            ])
            ->add('ville', EntityType::class, [
                'class' => Ville::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Sélectionner une ville',
            ])
            ->add('pays', EntityType::class, [
                'class' => Pays::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Sélectionner un pays',
            ])
            ->add('tarif', EntityType::class, [
                'class' => Tarifs::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Sélectionner un tarif',
            ])
            ->add('reglement', EntityType::class, [
                'class' => Reglement::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Sélectionner un règlement',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Clients::class,
        ]);
    }
}
