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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientFormType extends AbstractType
{
    public function __construct(private Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentDossier = $this->getCurrentDossier();

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

    private function getCurrentDossier(): ?\App\Entity\Dossier
    {
        $user = $this->security->getUser();
        return $user instanceof User ? $user->getCurrentDossier() : null;
    }
}
