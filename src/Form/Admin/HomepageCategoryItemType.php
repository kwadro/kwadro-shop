<?php

namespace App\Form\Admin;

use App\Entity\Category;
use App\Entity\HomepageCategoryItem;
use App\Repository\CategoryRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

final class HomepageCategoryItemType extends AbstractType
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
                'label' => 'admin.homepage_setting.category_item_enabled',
            ])
            ->add('position', IntegerType::class, [
                'required' => false,
                'label' => 'admin.homepage_setting.category_item_position',
                'help' => 'admin.homepage_setting.category_item_position_help',
                'attr' => ['min' => 0, 'max' => 999],
            ])
            ->add('category', ChoiceType::class, [
                'required' => true,
                'label' => 'admin.homepage_setting.category_item_category',
                'placeholder' => 'admin.homepage_setting.category_item_category_placeholder',
                'choices' => $categories,
                'choice_label' => static function (Category $category): string {
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
                    'data-ea-widget' => 'ea-autocomplete',
                ],
                'constraints' => [new NotNull()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HomepageCategoryItem::class,
            'label' => false,
            'translation_domain' => 'messages',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'homepage_category_item';
    }
}
