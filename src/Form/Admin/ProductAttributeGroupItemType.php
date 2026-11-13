<?php

namespace App\Form\Admin;

use App\Entity\ProductAttributeGroupItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProductAttributeGroupItemType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $translator = $this->translator;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($translator): void {
            $item = $event->getData();
            $attribute = $item instanceof ProductAttributeGroupItem ? $item->getAttribute() : null;
            $name = $attribute?->getName() ?? '';
            $globalShow = $attribute?->isShowInFilter() ?? false;
            $globalLabel = $translator->trans(
                $globalShow
                    ? 'admin.product_attribute_group.item_global_show_yes'
                    : 'admin.product_attribute_group.item_global_show_no',
                [],
                'messages',
            );
            $form = $event->getForm();

            $form
                ->add('position', IntegerType::class, [
                    'required' => false,
                    'label' => 'admin.product_attribute_group.item_position',
                    'attr' => ['min' => 0, 'max' => 9999],
                ])
                ->add('attributeLabel', TextType::class, [
                    'mapped' => false,
                    'required' => false,
                    'disabled' => true,
                    'label' => 'admin.product_attribute_group.item_attribute',
                    'data' => $name,
                ])
                ->add('globalShowInFilter', TextType::class, [
                    'mapped' => false,
                    'required' => false,
                    'disabled' => true,
                    'label' => 'admin.product_attribute_group.item_global_show_in_filter',
                    'help' => 'admin.product_attribute_group.item_global_show_in_filter_help',
                    'data' => $globalLabel,
                ])
                ->add('show_in_filter', CheckboxType::class, [
                    'required' => false,
                    'label' => 'admin.product_attribute_group.item_show_in_filter',
                    'help' => 'admin.product_attribute_group.item_show_in_filter_help',
                ])
                ->add('deleted', CheckboxType::class, [
                    'required' => false,
                    'label' => 'admin.product_attribute_group.item_deleted',
                    'help' => 'admin.product_attribute_group.item_deleted_help',
                ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductAttributeGroupItem::class,
            'label' => false,
            'translation_domain' => 'messages',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'product_attribute_group_item';
    }
}
