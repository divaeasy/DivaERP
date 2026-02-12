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

class LignepieceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('qte')
            ->add('pub')
            ->add('montant')
            ->add('remise')
           
            
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
