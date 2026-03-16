<?php

namespace App\Form;

use App\Entity\Dossier;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class UserEditFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            ->add('nom')
            ->add('prenom')
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Utilisateur' => 'ROLE_USER',
                    'Commercial' => 'ROLE_COMMERCIAL',
                    'Comptable' => 'ROLE_COMPTABLE',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'expanded' => true,
                'multiple' => true,
                'required' => false,
                'label' => 'Rôles',
                'attr' => ['class' => 'form-check-group'],
            ])
            ->add('dossiers', EntityType::class, [
                'class' => Dossier::class,
                'choice_label' => 'nom',
                'label' => 'Dossiers autorisés',
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'attr' => ['class' => 'form-control js-example-basic-single'],
            ])
            ->add('currentDossier', EntityType::class, [
                'class' => Dossier::class,
                'choice_label' => 'nom',
                'label' => 'Dossier actif',
                'required' => false,
                'attr' => ['class' => 'form-control js-example-basic-single'],
            ])
            ->add('isVerified', CheckboxType::class, [
                'required' => false,
                'label' => 'Email vérifié',
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['autocomplete' => 'new-password', 'placeholder' => 'Laisser vide pour ne pas changer'],
                'label' => 'Nouveau mot de passe',
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères',
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
