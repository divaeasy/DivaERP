<?php

namespace App\Form;

use App\Entity\Clients;
use App\Entity\CodeOperation;
use App\Entity\Devises;
use App\Entity\Entetepiece;
use App\Entity\Fournisseur;
use App\Entity\Prospects;
use App\Entity\Reglement;
use App\Entity\TiersInterne;
use App\Entity\User;
use App\Repository\TiersInterneRepository;
use App\Service\CodeOperationService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntetePieceFormType extends AbstractType
{
    public function __construct(
        private Security $security,
        private ManagerRegistry $doctrine,
        private CodeOperationService $codeOperationService,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $piece = $options['data'] ?? null;
        $isEdit = $piece instanceof Entetepiece && null !== $piece->getId();
        $readOnly = (bool) ($options['read_only'] ?? false);
        $tierOrigin = $this->normalizeTierOrigin($options['tier_origin'] ?? null);
        $isNewFixedTierOrigin = !$isEdit && in_array($tierOrigin, ['fournisseur', 'interne'], true);
        $currentDossier = $this->getCurrentDossier();
        $tierTypeChoices = $this->buildTierTypeChoices($tierOrigin, $isEdit);
        $initialTierType = $piece instanceof Entetepiece ? $piece->getTypet() : null;
        $initialTiers = $this->getTierChoices($currentDossier, $initialTierType);
        $initialTierId = $piece instanceof Entetepiece && $piece->getTierId() !== null
            ? (string) $piece->getTierId()
            : null;

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
                'choices' => $tierTypeChoices,
                'placeholder' => ($isEdit || $isNewFixedTierOrigin) ? false : 'Selectionner un type de tiers',
                'required' => true,
                'label' => 'Type de tiers',
                'disabled' => $readOnly || $isNewFixedTierOrigin,
                'attr' => [
                    'class' => 'js-tier-type',
                ],
            ])
            ->add('tierId', HiddenType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'js-tier-id-field',
                ],
            ])
            ->add('tierDestination', EntityType::class, [
                'class' => TiersInterne::class,
                'choice_label' => 'nom',
                'required' => false,
                'placeholder' => 'Selectionner une destination interne',
                'label' => 'Tiers destination (interne)',
                'disabled' => $readOnly,
                'query_builder' => function (TiersInterneRepository $repository) use ($currentDossier) {
                    $qb = $repository->createQueryBuilder('t')->orderBy('t.nom', 'ASC');
                    if ($currentDossier !== null) {
                        $qb->andWhere('t.dossier = :dossier')->setParameter('dossier', $currentDossier);
                    } else {
                        $qb->andWhere('1 = 0');
                    }
                    return $qb;
                },
            ])
            ->add('tierSelector', ChoiceType::class, [
                'mapped' => false,
                'choices' => $this->toChoiceMap($initialTiers),
                'data' => $initialTierId,
                'required' => false,
                'placeholder' => $isEdit ? false : 'Selectionner un tiers',
                'label' => 'Tiers',
                'disabled' => $readOnly,
                'attr' => [
                    'class' => 'form-control js-example-basic-single js-tier-selector',
                    'data-tier-reglements' => json_encode($this->buildTierReglementMap($initialTiers)),
                ],
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

        $this->addCodeOperationField(
            $builder,
            $piece instanceof Entetepiece ? $piece->getTypet() : null,
            $piece instanceof Entetepiece ? $piece->getType() : null,
            $piece instanceof Entetepiece ? $piece->getCodeOperation() : null,
            $readOnly,
            $isEdit
        );

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($currentDossier, $isEdit, $readOnly): void {
            $data = $event->getData();
            if (!$data instanceof Entetepiece) {
                return;
            }

            $tierChoices = $this->getTierChoices($currentDossier, $data->getTypet());
            $tierId = $data->getTierId();
            $event->getForm()->add('tierSelector', ChoiceType::class, [
                'mapped' => false,
                'choices' => $this->toChoiceMap($tierChoices),
                'data' => $tierId !== null ? (string) $tierId : null,
                'required' => false,
                'placeholder' => $isEdit ? false : 'Selectionner un tiers',
                'label' => 'Tiers',
                'disabled' => $readOnly,
                'attr' => [
                    'class' => 'form-control js-example-basic-single js-tier-selector',
                    'data-tier-reglements' => json_encode($this->buildTierReglementMap($tierChoices)),
                ],
            ]);

            $this->addCodeOperationField(
                $event->getForm(),
                $data->getTypet(),
                $data->getType(),
                $data->getCodeOperation(),
                $readOnly,
                $isEdit
            );
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($currentDossier, $isEdit, $readOnly): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $typet = (string) ($data['typet'] ?? $event->getForm()->get('typet')->getData() ?? '');
            $pieceType = (string) ($data['type'] ?? $event->getForm()->get('type')->getData() ?? 'Facture');
            $tierChoices = $this->getTierChoices($currentDossier, $typet);
            $selectedTier = trim((string) ($data['tierSelector'] ?? $data['tierId'] ?? ''));

            $event->getForm()->add('tierSelector', ChoiceType::class, [
                'mapped' => false,
                'choices' => $this->toChoiceMap($tierChoices),
                'data' => $selectedTier !== '' ? $selectedTier : null,
                'required' => false,
                'placeholder' => $isEdit ? false : 'Selectionner un tiers',
                'label' => 'Tiers',
                'disabled' => $readOnly,
                'attr' => [
                    'class' => 'form-control js-example-basic-single js-tier-selector',
                    'data-tier-reglements' => json_encode($this->buildTierReglementMap($tierChoices)),
                ],
            ]);

            $data['tierId'] = $selectedTier !== '' && ctype_digit($selectedTier)
                ? (int) $selectedTier
                : null;

            $selectedCodeOperationId = trim((string) ($data['codeOperation'] ?? ''));
            if ($selectedCodeOperationId !== '' && ctype_digit($selectedCodeOperationId)) {
                $selectedOperation = $this->doctrine->getRepository(CodeOperation::class)->find((int) $selectedCodeOperationId);
                if ($selectedOperation instanceof CodeOperation) {
                    $this->addCodeOperationField(
                        $event->getForm(),
                        $typet,
                        $pieceType,
                        $selectedOperation,
                        $readOnly,
                        $isEdit
                    );
                } else {
                    $this->addCodeOperationField(
                        $event->getForm(),
                        $typet,
                        $pieceType,
                        null,
                        $readOnly,
                        $isEdit
                    );
                }
            } else {
                $this->addCodeOperationField(
                    $event->getForm(),
                    $typet,
                    $pieceType,
                    null,
                    $readOnly,
                    $isEdit
                );
            }

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entetepiece::class,
            'read_only' => false,
            'tier_origin' => null,
        ]);
        $resolver->setAllowedTypes('read_only', 'bool');
        $resolver->setAllowedTypes('tier_origin', ['null', 'string']);
    }

    private function getCurrentDossier(): ?\App\Entity\Dossier
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->getCurrentDossier() : null;
    }

    /**
     * @return array<int, array{id: string, label: string, reglementId: string}>
     */
    private function getTierChoices(?\App\Entity\Dossier $dossier, ?string $typet): array
    {
        if ($dossier === null) {
            return [];
        }

        $normalizedType = $this->normalizeTierType($typet);
        if (!in_array($normalizedType, ['client', 'prospect', 'fournisseur', 'tiersinterne', 'interne'], true)) {
            return [];
        }

        $entityClass = match ($normalizedType) {
            'client' => Clients::class,
            'prospect' => Prospects::class,
            'fournisseur' => Fournisseur::class,
            'tiersinterne', 'interne' => TiersInterne::class,
            default => null,
        };

        if ($entityClass === null) {
            return [];
        }

        $items = $this->doctrine->getRepository($entityClass)->createQueryBuilder('t')
            ->where('t.dossier = :dossier')
            ->setParameter('dossier', $dossier)
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();

        $choices = [];
        foreach ($items as $item) {
            $id = method_exists($item, 'getId') ? (int) $item->getId() : 0;
            if ($id <= 0) {
                continue;
            }

            $label = method_exists($item, 'getNom') ? trim((string) $item->getNom()) : '';
            if ($label === '') {
                $label = sprintf('Tiers #%d', $id);
            }

            $reglementId = '';
            if (method_exists($item, 'getReglement')) {
                $reglement = $item->getReglement();
                if ($reglement !== null && method_exists($reglement, 'getId')) {
                    $reglementId = (string) ($reglement->getId() ?? '');
                }
            }

            $choices[] = [
                'id' => (string) $id,
                'label' => $label,
                'reglementId' => $reglementId,
            ];
        }

        return $choices;
    }

    /**
     * @param array<int, array{id: string, label: string, reglementId: string}> $tierChoices
     * @return array<string, string>
     */
    private function toChoiceMap(array $tierChoices): array
    {
        $map = [];
        foreach ($tierChoices as $tierChoice) {
            $label = trim((string) ($tierChoice['label'] ?? ''));
            $id = trim((string) ($tierChoice['id'] ?? ''));
            if ($label === '' || $id === '') {
                continue;
            }

            if (array_key_exists($label, $map)) {
                $label = sprintf('%s (#%s)', $label, $id);
            }

            $map[$label] = $id;
        }

        return $map;
    }

    /**
     * @param array<int, array{id: string, label: string, reglementId: string}> $tierChoices
     * @return array<string, string>
     */
    private function buildTierReglementMap(array $tierChoices): array
    {
        $map = [];
        foreach ($tierChoices as $tierChoice) {
            $id = (string) ($tierChoice['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $map[$id] = (string) ($tierChoice['reglementId'] ?? '');
        }

        return $map;
    }

    private function normalizeTierType(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($ascii) && $ascii !== '') {
            $normalized = strtolower($ascii);
        }

        return (string) preg_replace('/[^a-z0-9]/', '', $normalized);
    }

    /**
     * @return array<string, string>
     */
    private function buildTierTypeChoices(?string $tierOrigin, bool $isEdit): array
    {
        if ($isEdit) {
            return [
                'Client' => 'Client',
                'Prospect' => 'Prospect',
                'Fournisseur' => 'Fournisseur',
                'Interne' => 'Interne',
                'VAT' => 'VAT',
            ];
        }

        if ($tierOrigin === 'fournisseur') {
            return [
                'Fournisseur' => 'Fournisseur',
            ];
        }

        if ($tierOrigin === 'client') {
            return [
                'Client' => 'Client',
                'Prospect' => 'Prospect',
            ];
        }

        if ($tierOrigin === 'interne') {
            return [
                'Interne' => 'Interne',
            ];
        }

        return [
            'Client' => 'Client',
            'Prospect' => 'Prospect',
            'Fournisseur' => 'Fournisseur',
            'Interne' => 'Interne',
            'VAT' => 'VAT',
        ];
    }

    private function normalizeTierOrigin(?string $value): ?string
    {
        return match ($this->normalizeTierType($value)) {
            'fournisseur' => 'fournisseur',
            'interne', 'tierinterne', 'tiersinterne' => 'interne',
            'client', 'prospect' => 'client',
            default => null,
        };
    }

    private function addCodeOperationField(
        FormBuilderInterface|\Symfony\Component\Form\FormInterface $form,
        ?string $tierType,
        ?string $pieceType,
        ?CodeOperation $selected,
        bool $readOnly,
        bool $isEdit
    ): void {
        $operations = $this->codeOperationService->getActiveForPiece($tierType, $pieceType);
        $selected = $selected instanceof CodeOperation ? $selected : null;
        if (
            $selected === null
            && !$this->codeOperationService->needsManualCodeOperationChoice($tierType, $pieceType)
            && count($operations) === 1
        ) {
            $selected = $operations[0];
        }

        $form->add('codeOperation', EntityType::class, [
            'class' => CodeOperation::class,
            'choices' => $operations,
            'choice_label' => static fn (CodeOperation $operation): string => sprintf(
                '%s (%s)',
                (string) $operation->getLibelle(),
                $operation->getSens()->label()
            ),
            'placeholder' => 'Selectionner un code operation',
            'required' => false,
            'label' => 'Code operation',
            'disabled' => $readOnly,
            'data' => $selected,
            'attr' => [
                'class' => 'form-control js-example-basic-single js-code-operation',
            ],
        ]);
    }
}
