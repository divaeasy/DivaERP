<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Tarifs;
use App\Entity\Unite;
use App\Entity\User;
use App\Repository\TarifsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
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
                        'maxSizeMessage' => 'Le fichier dépasse 2 Mo',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Seuls les fichiers PNG, JPG et WebP sont acceptés',
                    ]),
                ],
            ])
            ->add('unite', EntityType::class, [
                'class' => Unite::class,
                'choice_label' => 'libelle',
                'required' => false,
                'placeholder' => 'Sélectionner une unité',
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
        ;
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
