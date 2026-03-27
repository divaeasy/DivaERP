<?php

namespace App\Form;

use App\Entity\Devises;
use App\Entity\Dossier;
use App\Entity\Theme;
use App\Repository\ThemeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DossierFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('adresse')
            ->add('codepostal', null, ['required' => false])
            ->add('ville', null, ['required' => false])
            ->add('pays', null, ['required' => false])
            ->add('logoFile', FileType::class, [
                'label' => 'Logo',
                'mapped' => false,
                'required' => false,
                'help' => 'PNG ou JPG, 1 Mo max.',
            ])
            ->add('rc')
            ->add('siret', null, ['required' => false])
            ->add('naf', null, ['required' => false])
            ->add('tvaintra', null, ['required' => false])
            ->add('email', null, ['required' => false])
            ->add('tel', null, ['required' => false])
            ->add('iban', null, ['required' => false])
            ->add('bic', null, ['required' => false])
            ->add('devisno', IntegerType::class, ['required' => false])
            ->add('cmdno', IntegerType::class, ['required' => false])
            ->add('blno', IntegerType::class, ['required' => false])
            ->add('factureno', IntegerType::class, ['required' => false])
            ->add('theme', EntityType::class, [
                'label' => false,
                'required' => true,
                'expanded' => true,
                'multiple' => false,
                'class' => Theme::class,
                'choice_label' => 'name',
                'query_builder' => static function (ThemeRepository $themeRepository) {
                    return $themeRepository->createQueryBuilder('t')
                        ->orderBy('t.isSystem', 'DESC')
                        ->addOrderBy('t.name', 'ASC');
                },
                'choice_attr' => static function (?Theme $theme): array {
                    if (!$theme instanceof Theme) {
                        return ['class' => 'theme-radio-input'];
                    }

                    return [
                        'class' => 'theme-radio-input',
                        'data-theme-id' => (string) $theme->getId(),
                        'data-theme-code' => (string) $theme->getCode(),
                        'data-theme-name' => (string) $theme->getName(),
                        'data-theme-description' => (string) ($theme->getDescription() ?? ''),
                        'data-theme-primary' => (string) $theme->getPrimaryColor(),
                        'data-theme-secondary' => (string) $theme->getSecondaryColor(),
                        'data-theme-accent' => (string) $theme->getAccentColor(),
                        'data-theme-text' => (string) $theme->getTextColor(),
                        'data-theme-bg' => (string) $theme->getBackgroundColor(),
                    ];
                },
            ])
            ->add('devise', EntityType::class, [
                'class' => Devises::class,
                'choice_label' => 'code',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dossier::class,
        ]);
    }
}

