<?php

namespace App\Form\Admin;

use App\Entity\Category;
use App\Entity\ShopMegaMenuItem;
use App\Repository\CategoryRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

final class ShopMegaMenuItemType extends AbstractType
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $categories = $this->categoryRepository->findEnabledInTreeOrder();

        $builder
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'admin.shop_mega_menu.item_enabled',
            ])
            ->add('position', IntegerType::class, [
                'required' => false,
                'label' => 'admin.shop_mega_menu.item_position',
                'help' => 'admin.shop_mega_menu.item_position_help',
                'attr' => ['min' => 0, 'max' => 999],
            ])
            ->add('category', ChoiceType::class, [
                'required' => true,
                'label' => 'admin.shop_mega_menu.item_category',
                'placeholder' => 'admin.shop_mega_menu.item_category_placeholder',
                'choices' => $categories,
                'choice_label' => static function (Category $category): string {
                    // Level 0 = Default root; storefront level-1 starts at entity level 1.
                    $depth = max(0, $category->getLevel() - 1);

                    return sprintf(
                        '%s%s [%s]',
                        str_repeat('— ', $depth),
                        $category->getName(),
                        $category->getSlug(),
                    );
                },
                'choice_value' => static fn (?Category $category): string => $category?->getId() !== null
                    ? (string) $category->getId()
                    : '',
                'attr' => [
                    // EasyAdmin Tom Select: searchable dropdown (filter by name/slug).
                    'data-ea-widget' => 'ea-autocomplete',
                ],
                'constraints' => [new NotNull()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ShopMegaMenuItem::class,
            'label' => false,
            'translation_domain' => 'messages',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'shop_mega_menu_item';
    }
}
