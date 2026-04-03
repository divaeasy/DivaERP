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
        $piece = $options['data'] ?? null;
        $isEdit = $piece instanceof Entetepiece && null !== $piece->getId();
        $readOnly = (bool) ($options['read_only'] ?? false);
        $currentDossier = $this->getCurrentDossier();

        $builder
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Devis' => 'Devis',
                    'Commande' => 'Commande',
                    'BL' => 'BL',
                    'Facture' => 'Facture',
                ],
                'placeholder' => $isEdit ? false : 'Selectionner un type',
                'required' => true,
                'label' => 'Type de piece',
                'disabled' => $isEdit || $readOnly,
            ])
            ->add('typet', ChoiceType::class, [
                'choices' => [
                    'Client' => 'Client',
                    'Prospect' => 'Prospect',
                    'Fournisseur' => 'Fournisseur',
                    'VAT' => 'VAT',
                ],
                'placeholder' => $isEdit ? false : 'Selectionner un type de tiers',
                'required' => true,
                'label' => 'Type de tiers',
                'disabled' => $readOnly,
            ])
            ->add('pieceno', null, [
                'disabled' => $readOnly,
            ])
            ->add('pieceref', null, [
                'disabled' => $readOnly,
            ])
            ->add('remise', null, [
                'disabled' => $readOnly,
            ])
            ->add('delai', null, [
                'widget' => 'single_text',
                'disabled' => $readOnly,
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'Brouillon' => 'Brouillon',
                    'Active' => 'Active',
                    'Validee' => "Valid\u{00E9}e",
                ],
                'placeholder' => $isEdit ? false : 'Selectionner un statut',
                'required' => true,
                'label' => 'Statut',
                'disabled' => $readOnly,
            ])
            ->add('edition', null, [
                'disabled' => $readOnly,
            ])
            ->add('rapport', null, [
                'disabled' => $readOnly,
            ])
            ->add('client', EntityType::class, [
                'class' => Clients::class,
                'choice_label' => 'nom',
                'placeholder' => $isEdit ? false : 'Selectionner un client',
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
                'disabled' => $readOnly,
            ])
            ->add('devise', EntityType::class, [
                'class' => Devises::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => $isEdit ? false : 'Selectionner une devise',
                'disabled' => $readOnly,
            ])
            ->add('reglement', EntityType::class, [
                'class' => Reglement::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => $isEdit ? false : 'Selectionner un reglement',
                'disabled' => $readOnly,
            ])
            ->add('datep', null, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Date piece',
                'disabled' => $readOnly,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entetepiece::class,
            'read_only' => false,
        ]);
        $resolver->setAllowedTypes('read_only', 'bool');
    }

    private function getCurrentDossier(): ?\App\Entity\Dossier
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->getCurrentDossier() : null;
    }
}

