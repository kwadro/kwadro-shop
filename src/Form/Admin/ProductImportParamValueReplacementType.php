<?php

namespace App\Form\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\ProductAttributeRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductImportParamValueReplacementType extends AbstractType
{
    /** @var array<string, int>|null */
    private ?array $attributeChoices = null;

    /** @var array<int, int> */
    private array $attributeCategoryIds = [];

    /** @var array<int, string> */
    private array $attributeNames = [];

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductAttributeRepository $attributeRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('from', TextType::class, [
                'required' => true,
                'label' => 'admin.product_import.param_value_from',
            ])
            ->add('to', TextType::class, [
                'required' => true,
                'label' => 'admin.product_import.param_value_to',
            ])
            ->add('category_id', ChoiceType::class, [
                'required' => false,
                'label' => 'admin.product_import.param_value_category',
                'placeholder' => 'admin.product_import.param_value_category_all',
                'choices' => $this->categoryChoices(),
                'attr' => ['data-ea-widget' => 'ea-autocomplete'],
            ])
            ->add('attribute_id', ChoiceType::class, [
                'required' => false,
                'label' => 'admin.product_import.param_value_attribute',
                'placeholder' => 'admin.product_import.param_value_attribute_all',
                'choices' => $this->attributeChoices(),
                'choice_attr' => function (mixed $choice): array {
                    $id = (int) $choice;

                    return [
                        'data-category-id' => (string) ($this->attributeCategoryIds[$id] ?? ''),
                        'data-attribute-name' => $this->attributeNames[$id] ?? '',
                    ];
                },
                'attr' => ['data-ea-widget' => 'ea-autocomplete'],
            ]);

        $emptyToNull = static fn (mixed $value): ?int => (int) $value > 0 ? (int) $value : null;
        $builder->get('category_id')->addModelTransformer(new CallbackTransformer($emptyToNull, $emptyToNull));
        $builder->get('attribute_id')->addModelTransformer(new CallbackTransformer($emptyToNull, $emptyToNull));
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
    private function categoryChoices(): array
    {
        $choices = [];
        foreach ($this->categoryRepository->findAllOrdered() as $category) {
            if (!$category instanceof Category || $category->isDefault() || $category->getId() === null) {
                continue;
            }
            $choices[$this->categoryLabel($category)] = $category->getId();
        }

        return $choices;
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
            ->select('a.id AS id, a.name AS name, g.name AS group_name, c.id AS category_id, c.name AS category_name')
            ->innerJoin('a.group', 'g')
            ->leftJoin('g.category', 'c')
            ->orderBy('c.name', 'ASC')
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
            $categoryId = (int) ($row['category_id'] ?? 0);
            $this->attributeNames[$id] = $name;
            if ($categoryId > 0) {
                $this->attributeCategoryIds[$id] = $categoryId;
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

    private function categoryLabel(Category $category): string
    {
        $depth = max(0, $category->getLevel() - 1);
        $parent = $category->getParent();
        $parentName = $parent instanceof Category && !$parent->isDefault()
            ? $parent->getName().' / '
            : '';

        return sprintf(
            '%s%s%s [%s]',
            str_repeat('— ', $depth),
            $parentName,
            $category->getName(),
            $category->getSlug(),
        );
    }
}
