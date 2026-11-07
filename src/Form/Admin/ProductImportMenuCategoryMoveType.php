<?php

namespace App\Form\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductImportMenuCategoryMoveType extends AbstractType
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choiceOptions = [
            'choices' => $this->categoryChoices(),
            'attr' => ['data-ea-widget' => 'ea-autocomplete'],
        ];

        $builder
            ->add('category_id', ChoiceType::class, $choiceOptions + [
                'required' => true,
                'label' => 'admin.product_import.menu_move_category',
                'placeholder' => 'admin.product_import.menu_category_placeholder',
            ])
            ->add('parent_id', ChoiceType::class, $choiceOptions + [
                'required' => true,
                'label' => 'admin.product_import.menu_move_parent',
                'placeholder' => 'admin.product_import.menu_move_parent_placeholder',
            ]);
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
        foreach ($this->categoryRepository->findEnabledInTreeOrder() as $category) {
            $id = $category->getId();
            if ($id === null) {
                continue;
            }
            $choices[$this->categoryLabel($category)] = $id;
        }

        return $choices;
    }

    public function categoryLabel(Category $category): string
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
