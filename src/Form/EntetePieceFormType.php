<?php

namespace App\Form;

use App\Entity\Clients;
use App\Entity\Devises;
use App\Entity\Dossier;
use App\Entity\Entetepiece;
use App\Entity\Reglement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntetePieceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'choices'  => [
                    'Devis' => 'Devis',
                    'Commande' => 'Commande',
                    'BL' => 'BL',
                    'Facture' => 'Facture'
                ],
                'placeholder' => ' ',
                'required' => true,
                'empty_data' => 'Non',
                'label' => 'Type de pièce',
                'data' => 'Facture' 
                ])
            ->add('typet', ChoiceType::class, [
                'choices'  => [
                    'Client' => 'Client',
                    'Prospect' => 'Prospect',
                    'Fournisseur' => 'Fournisseur'
                ],
                'placeholder' => ' ',
                'required' => true,
                'empty_data' => 'Non',
                'label' => 'Type de tiers',
                'data' => 'Client' 
                ])
            ->add('pieceno')
            ->add('pieceref')
            ->add('remise')
            ->add('delai', null, [
                'widget' => 'single_text',
            ])
            ->add('statut', ChoiceType::class, [
                'choices'  => [
                    'Brouillon' => 'Brouillon',
                    'Active' => 'Active',
                    'Périmée' => 'Périmée'
                ],
                'placeholder' => ' ',
                'required' => true,
                'empty_data' => 'Non',
                'label' => 'Statut',
                'data' => 'Active' 
                ])
            ->add('edition')
            ->add('rapport')
            ->add('dossier', EntityType::class, [
                'class' => Dossier::class,
                'choice_label' => 'nom',
            ])
            ->add('client', EntityType::class, [
                'class' => Clients::class,
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionner un client',
            ])
            ->add('devise', EntityType::class, [
                'class' => Devises::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Sélectionner une devise',
            ])
            ->add('reglement', EntityType::class, [
                'class' => Reglement::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Sélectionner un règlement',
            ])
            ->add('datep', null, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Date pièce',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entetepiece::class,
        ]);
    }
}
