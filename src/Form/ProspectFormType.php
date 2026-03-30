<?php

namespace App\Form;

use App\Entity\Pays;
use App\Entity\Prospects;
use App\Entity\Ville;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class ProspectFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $prospect = $options['data'] ?? null;
        $isEdit = $prospect instanceof Prospects && null !== $prospect->getId();

        $builder
        ->add('nom')
        ->add('adr1')
        ->add('adr2', null, ['required' => false])
        ->add('rue', null, [
            'required' => true,
            'constraints' => [
                new NotBlank(['message' => 'La rue est obligatoire.']),
            ],
        ])
        ->add('codepostal', null, ['required' => false])
        ->add('tel', null, ['required' => false])
        ->add('email', null, ['required' => false])
        ->add('web', null, ['required' => false])
        ->add('linkedin', null, ['required' => false])
        ->add('ville', EntityType::class, [
            'class' => Ville::class,
            'choice_label' => 'libelle',
            'required' => true,
            'placeholder' => $isEdit ? false : 'Selectionner une ville',
            'constraints' => [
                new NotNull(['message' => 'La ville est obligatoire.']),
            ],
        ])
        ->add('pays', EntityType::class, [
            'class' => Pays::class,
            'choice_label' => 'libelle',
            'placeholder' => $isEdit ? false : 'Selectionner un pays',
        ])
       
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Prospects::class,
        ]);
    }
}
