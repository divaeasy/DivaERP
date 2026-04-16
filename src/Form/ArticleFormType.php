<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Fournisseur;
use App\Entity\NatureProduction;
use App\Entity\Tarifs;
use App\Entity\Unite;
use App\Entity\User;
use App\Enum\ArticleModeGestion;
use App\Enum\ArticleModeSuivi;
use App\Enum\SortiStockMode;
use App\Repository\FournisseurRepository;
use App\Repository\TarifsRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ArticleFormType extends AbstractType
{
    public function __construct(private Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentDossier = $this->getCurrentDossier();

        $builder
            ->add('libelle')
            ->add('imageFile', FileType::class, [
                'label' => "Image de l'article",
                'mapped' => false,
                'required' => false,
                'help' => 'PNG, JPG ou WebP, 2 Mo max. L image sera affichee en vignette dans le tableau.',
                'attr' => [
                    'accept' => 'image/*',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'maxSizeMessage' => 'Le fichier depasse 2 Mo',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Seuls les fichiers PNG, JPG et WebP sont acceptes',
                    ]),
                ],
            ])
            ->add('unite', EntityType::class, [
                'class' => Unite::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Selectionner une unite',
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
            ->add('modeGestion', EnumType::class, [
                'class' => ArticleModeGestion::class,
                'choice_label' => static fn (ArticleModeGestion $choice): string => $choice->value,
            ])
            ->add('modeSuivi', EnumType::class, [
                'class' => ArticleModeSuivi::class,
                'choice_label' => static fn (ArticleModeSuivi $choice): string => $choice->value,
            ])
            ->add('natureProduction', EntityType::class, [
                'class' => NatureProduction::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Selectionner une nature de production',
            ])
            ->add('sortiStock', EnumType::class, [
                'class' => SortiStockMode::class,
                'expanded' => true,
                'multiple' => false,
                'choice_label' => static fn (SortiStockMode $choice): string => $choice->value,
            ])
            ->add('fournisseurHabituel', EntityType::class, [
                'class' => Fournisseur::class,
                'choice_label' => 'nom',
                'required' => false,
                'placeholder' => 'Selectionner un fournisseur',
                'query_builder' => function (FournisseurRepository $repository) use ($currentDossier) {
                    $qb = $repository->createQueryBuilder('f')
                        ->orderBy('f.nom', 'ASC');

                    if ($currentDossier !== null) {
                        $qb->andWhere('f.dossier = :dossier')
                            ->setParameter('dossier', $currentDossier);
                    } else {
                        $qb->andWhere('1 = 0');
                    }

                    return $qb;
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }

    private function getCurrentDossier(): ?\App\Entity\Dossier
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->getCurrentDossier() : null;
    }
}
