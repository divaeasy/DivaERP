<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Dossier;
use App\Entity\Entetepiece;
use App\Entity\Lignepiece;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class LignepieceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('qte', null, [
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La quantité est obligatoire.']),
                    new Assert\GreaterThan(['value' => 0, 'message' => 'La quantité doit être supérieure à 0.']),
                ],
            ])
            ->add('pub', null, [
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le prix unitaire est obligatoire.']),
                    new Assert\GreaterThan(['value' => 0, 'message' => 'Le prix unitaire doit être supérieur à 0.']),
                ],
            ])
            ->add('montant', null, [
                'required' => false,
            ])
            ->add('remise', null, [
                'required' => false,
                'constraints' => [
                    new Assert\Range([
                        'min' => 0,
                        'max' => 100,
                        'notInRangeMessage' => 'La remise doit être comprise entre 0 et 100.',
                    ]),
                ],
            ])
           
            
            ->add('article', EntityType::class, [
                'class' => Article::class,
                'choice_label' => 'libelle',
                'placeholder' => ' ',
                'required' => true,
                'expanded' => false,
                'multiple' => false
            ])
            
            
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lignepiece::class,
        ]);
    }
}
