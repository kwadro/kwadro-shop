<?php

namespace App\Form\Admin;

use App\Repository\ProductAttributeRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One replacement rule block: one or more attributes + from/to pairs.
 * Category is implied by the selected attributes (no separate category field).
 */
final class ProductImportParamValueReplacementGroupType extends AbstractType
{
    /** @var array<string, int>|null */
    private ?array $attributeChoices = null;

    public function __construct(
        private readonly ProductAttributeRepository $attributeRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('attribute_ids', ChoiceType::class, [
                'required' => false,
                'multiple' => true,
                'label' => 'admin.product_import.param_value_attribute',
                'choices' => $this->attributeChoices(),
                'attr' => [
                    'data-ea-widget' => 'ea-autocomplete',
                    'class' => 'param-value-attribute-select',
                    'data-param-value-attribute-multi' => '1',
                ],
            ])
            ->add('replacements', CollectionType::class, [
                'entry_type' => ProductImportParamValuePairType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__replacement__',
                'label' => 'admin.product_import.param_value_replacements_list',
                'entry_options' => ['label' => false],
                'attr' => [
                    'class' => 'param-value-replacements-collection',
                    'data-param-value-nested-collection' => 'replacements',
                ],
            ])
            // Preserves legacy "whole category" rules (attribute_id empty, category_id set).
            ->add('category_id', HiddenType::class, [
                'required' => false,
            ]);

        $builder->get('attribute_ids')->addModelTransformer(new CallbackTransformer(
            static function (mixed $value): array {
                if (!\is_array($value)) {
                    return [];
                }
                $ids = [];
                foreach ($value as $item) {
                    $id = (int) $item;
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }

                return array_values(array_unique($ids));
            },
            static function (mixed $value): array {
                if (!\is_array($value)) {
                    return [];
                }
                $ids = [];
                foreach ($value as $item) {
                    $id = (int) $item;
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }

                return array_values(array_unique($ids));
            },
        ));

        $builder->get('category_id')->addModelTransformer(new CallbackTransformer(
            static fn (mixed $value): string => (int) $value > 0 ? (string) (int) $value : '',
            static fn (mixed $value): ?int => (int) $value > 0 ? (int) $value : null,
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
            'translation_domain' => 'messages',
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function attributeChoices(): array
    {
        if ($this->attributeChoices !== null) {
            return $this->attributeChoices;
        }

        $rows = $this->attributeRepository->createQueryBuilder('a')
            ->select('a.id AS id, a.name AS name, g.name AS group_name, c.name AS category_name')
            ->innerJoin('a.group', 'g')
            ->leftJoin('g.category', 'c')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->addOrderBy('a.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $choices = [];
        $used = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($id < 1 || $name === '') {
                continue;
            }
            $scope = trim((string) ($row['category_name'] ?? ''));
            if ($scope === '') {
                $scope = trim((string) ($row['group_name'] ?? ''));
            }
            $label = $scope !== '' ? $scope.' / '.$name : $name;
            if (isset($used[$label])) {
                $label .= ' ['.$id.']';
            }
            $used[$label] = true;
            $choices[$label] = $id;
        }

        return $this->attributeChoices = $choices;
    }
}
