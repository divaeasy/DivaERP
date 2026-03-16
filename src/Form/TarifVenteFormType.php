<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Clients;
use App\Entity\Devises;
use App\Entity\Dossier;
use App\Entity\Tarifs;
use App\Entity\Tarifvente;
use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\ClientsRepository;
use App\Repository\DossierRepository;
use App\Repository\TarifsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TarifVenteFormType extends AbstractType
{
    public function __construct(private Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentDossier = $this->getCurrentDossier();

        $builder
            ->add('dateeffet', DateType::class, [
                'widget' => 'single_text',
                // this is actually the default format for single_text                
                //'format' => 'dd-MM-yyyy  HH:mm',
                'data'   => new \DateTime(),
                'required' => true
            ])
            ->add('prix')
            ->add('tarif', EntityType::class, [
                'class' => Tarifs::class,
                'choice_label' => 'libelle',
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
            ->add('article', EntityType::class, [
                'class' => Article::class,
                'choice_label' => 'libelle',
                'query_builder' => function (ArticleRepository $repository) use ($currentDossier) {
                    $qb = $repository->createQueryBuilder('a')
                        ->orderBy('a.libelle', 'ASC');
                    if ($currentDossier !== null) {
                        $qb->andWhere('a.dossier = :dossier')
                           ->setParameter('dossier', $currentDossier);
                    } else {
                        $qb->andWhere('1 = 0');
                    }
                    return $qb;
                },
            ])
            ->add('client', EntityType::class, [
                'class' => Clients::class,
                'choice_label' => 'nom',
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
            ])
            ->add('dossier', EntityType::class, [
                'class' => Dossier::class,
                'choice_label' => 'nom',
                'data' => $currentDossier,
                'query_builder' => function (DossierRepository $repository) use ($currentDossier) {
                    $qb = $repository->createQueryBuilder('d')->orderBy('d.nom', 'ASC');
                    if ($currentDossier !== null) {
                        $qb->where('d.id = :id')->setParameter('id', $currentDossier->getId());
                    }
                    return $qb;
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tarifvente::class,
        ]);
    }

    private function getCurrentDossier(): ?\App\Entity\Dossier
    {
        $user = $this->security->getUser();
        return $user instanceof User ? $user->getCurrentDossier() : null;
    }
}
