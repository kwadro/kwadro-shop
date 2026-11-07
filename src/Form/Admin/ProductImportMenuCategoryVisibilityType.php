<?php

namespace App\Form\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductImportMenuCategoryVisibilityType extends AbstractType
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category_id', ChoiceType::class, [
                'required' => true,
                'label' => 'admin.product_import.menu_top_category',
                'placeholder' => 'admin.product_import.menu_category_placeholder',
                'choices' => $this->categoryChoices(),
                'attr' => ['data-ea-widget' => 'ea-autocomplete'],
            ])
            ->add('show', ChoiceType::class, [
                'required' => true,
                'label' => 'admin.product_import.menu_show_in_menu',
                'choices' => [
                    'admin.product_import.yes' => '1',
                    'admin.product_import.no' => '0',
                ],
                'placeholder' => false,
            ]);

        $builder->get('show')->addModelTransformer(new CallbackTransformer(
            static fn (mixed $value): string => $value === false || $value === 0 || $value === '0' ? '0' : '1',
            static fn (mixed $value): bool => $value === '1' || $value === 1 || $value === true,
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
