<?php

namespace App\Form;

use App\Entity\Depot;
use App\Entity\Pays;
use App\Entity\TiersInterne;
use App\Entity\User;
use App\Entity\Ville;
use App\Repository\TiersInterneRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class DepotFormType extends AbstractType
{
    public function __construct(private Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentDossier = $this->getCurrentDossier();

        $builder
            ->add('libelle', null, [
                'constraints' => [
                    new NotBlank(['message' => 'Le libelle est obligatoire.']),
                ],
            ])
            ->add('tiersInterne', EntityType::class, [
                'class' => TiersInterne::class,
                'choice_label' => 'nom',
                'required' => false,
                'placeholder' => 'Selectionner un tiers interne',
                'query_builder' => function (TiersInterneRepository $repository) use ($currentDossier) {
                    $qb = $repository->createQueryBuilder('t')
                        ->orderBy('t.nom', 'ASC');

                    if ($currentDossier !== null) {
                        $qb->andWhere('t.dossier = :dossier')
                            ->setParameter('dossier', $currentDossier);
                    } else {
                        $qb->andWhere('1 = 0');
                    }

                    return $qb;
                },
            ])
            ->add('adr1', null, ['required' => false])
            ->add('adr2', null, ['required' => false])
            ->add('rue', null, ['required' => false])
            ->add('codepostal', null, ['required' => false])
            ->add('ville', EntityType::class, [
                'class' => Ville::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Selectionner une ville',
            ])
            ->add('pays', EntityType::class, [
                'class' => Pays::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Selectionner un pays',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Depot::class,
        ]);
    }

    private function getCurrentDossier(): ?\App\Entity\Dossier
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->getCurrentDossier() : null;
    }
}
