<?php

namespace App\Form;

use App\Entity\Clients;
use App\Entity\Pays;
use App\Entity\Reglement;
use App\Entity\Tarifs;
use App\Entity\User;
use App\Entity\Ville;
use App\Repository\TarifsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;

class ClientFormType extends AbstractType
{
    public function __construct(private Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentDossier = $this->getCurrentDossier();

        $builder
            ->add('nom', null, [
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire.']),
                ],
            ])
            ->add('adr1', null, [
                'constraints' => [
                    new NotBlank(['message' => 'L\'adresse ligne 1 est obligatoire.']),
                ],
            ])
            ->add('adr2', null, ['required' => false])
            ->add('rue', null, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'La rue est obligatoire.']),
                ],
            ])
            ->add('codepostal', null, ['required' => false])
            ->add('tel', null, [
                'required' => false,
                'constraints' => [
                    new Regex([
                        'pattern' => '/^[+]?[0-9\s\-()\.]{7,20}$/',
                        'message' => 'Format téléphone invalide',
                        'groups' => 'Default'
                    ])
                ]
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'constraints' => [
                    new Email(['message' => 'Format email invalide'])
                ]
            ])
            ->add('web', null, ['required' => false])
            ->add('linkedin', null, ['required' => false])
            ->add('ville', EntityType::class, [
                'class' => Ville::class,
                'choice_label' => 'libelle',
                'required' => true,
                'placeholder' => 'Selectionner une ville',
                'constraints' => [
                    new NotNull(['message' => 'La ville est obligatoire.']),
                ],
            ])
            ->add('pays', EntityType::class, [
                'class' => Pays::class,
                'choice_label' => 'libelle',
                'required' => true,
                'placeholder' => 'Selectionner un pays',
                'constraints' => [
                    new NotNull(['message' => 'Le pays est obligatoire.']),
                ],
            ])
            ->add('tarif', EntityType::class, [
                'class' => Tarifs::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Selectionner un tarif',
                'query_builder' => function (TarifsRepository $repository) use ($currentDossier) {
                    $qb = $repository->createQueryBuilder('t')
                        ->orderBy('t.libelle', 'ASC');
                    if ($currentDossier !== null) {
                        $qb->andWhere('t.dossier = :dossier')
                           ->setParameter('dossier', $currentDossier);
                    } else {
                        $qb->andWhere('1 = 0');
                    }

                    return $qb;
                },
            ])
            ->add('reglement', EntityType::class, [
                'class' => Reglement::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Selectionner un reglement',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Clients::class,
        ]);
    }

    private function getCurrentDossier(): ?\App\Entity\Dossier
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->getCurrentDossier() : null;
    }
}

