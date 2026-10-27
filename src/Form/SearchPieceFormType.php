<?php

namespace App\Form;

use App\Enum\SensEnum;
use App\Model\SearchPiece;
use App\Repository\CodeOperationRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchPieceFormType extends AbstractType
{
    public function __construct(private readonly CodeOperationRepository $codeOperationRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $operations = $this->codeOperationRepository->findBy(['isActive' => true], ['libelle' => 'ASC']);
        $operationChoices = [];
        foreach ($operations as $operation) {
            $label = trim((string) $operation->getLibelle());
            if ($label === '' || $operation->getId() === null) {
                continue;
            }
            $operationChoices[$label] = (string) $operation->getId();
        }

        $builder
            ->add('pieceref', TextType::class, [
                'attr' => [
                    'placeholder' => 'Rechercher par reference...',
                ],
                'empty_data' => '',
                'required' => false,
            ])
            ->add('statut', TextType::class, [
                'attr' => [
                    'placeholder' => 'Rechercher par statut...',
                ],
                'empty_data' => '',
                'required' => false,
            ])
            ->add('codeOperationId', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'Code operation',
                'choices' => $operationChoices,
                'empty_data' => '',
            ])
            ->add('sens', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'Sens',
                'choices' => [
                    'Entree' => SensEnum::DEBIT->value,
                    'Sortie' => SensEnum::CREDIT->value,
                ],
                'empty_data' => '',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SearchPiece::class,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
