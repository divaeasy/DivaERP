<?php

namespace App\Form;

use App\Entity\Clients;
use App\Entity\Devises;
use App\Entity\Entetepiece;
use App\Entity\Reglement;
use App\Entity\User;
use App\Repository\ClientsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntetePieceFormType extends AbstractType
{
    public function __construct(private Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentDossier = $this->getCurrentDossier();

        $builder
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Devis' => 'Devis',
                    'Commande' => 'Commande',
                    'BL' => 'BL',
                    'Facture' => 'Facture',
                ],
                'placeholder' => ' ',
                'required' => true,
                'empty_data' => 'Non',
                'label' => 'Type de piece',
                'data' => 'Facture',
            ])
            ->add('typet', ChoiceType::class, [
                'choices' => [
                    'Client' => 'Client',
                    'Prospect' => 'Prospect',
                    'Fournisseur' => 'Fournisseur',
                ],
                'placeholder' => ' ',
                'required' => true,
                'empty_data' => 'Non',
                'label' => 'Type de tiers',
                'data' => 'Client',
            ])
            ->add('pieceno')
            ->add('pieceref')
            ->add('remise')
            ->add('delai', null, [
                'widget' => 'single_text',
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'Brouillon' => 'Brouillon',
                    'Active' => 'Active',
                    'Perimee' => 'Perimee',
                ],
                'placeholder' => ' ',
                'required' => true,
                'empty_data' => 'Non',
                'label' => 'Statut',
                'data' => 'Active',
            ])
            ->add('edition')
            ->add('rapport')
            ->add('client', EntityType::class, [
                'class' => Clients::class,
                'choice_label' => 'nom',
                'placeholder' => 'Selectionner un client',
                'choice_attr' => static function (?Clients $client): array {
                    return [
                        'data-reglement-id' => (string) ($client?->getReglement()?->getId() ?? ''),
                    ];
                },
                'query_builder' => function (ClientsRepository $repository) use ($currentDossier) {
                    $qb = $repository->createQueryBuilder('c')
                        ->orderBy('c.nom', 'ASC');
                    if ($currentDossier !== null) {
                        $qb->andWhere('c.dossier = :dossier')
                            ->setParameter('dossier', $currentDossier);
                    } else {
                        $qb->andWhere('1 = 0');
                    }

                    return $qb;
                },
            ])
            ->add('devise', EntityType::class, [
                'class' => Devises::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Selectionner une devise',
            ])
            ->add('reglement', EntityType::class, [
                'class' => Reglement::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Selectionner un reglement',
            ])
            ->add('datep', null, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Date piece',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entetepiece::class,
        ]);
    }

    private function getCurrentDossier(): ?\App\Entity\Dossier
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->getCurrentDossier() : null;
    }
}

