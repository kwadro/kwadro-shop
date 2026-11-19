<?php

namespace App\Form\Admin;

use App\Entity\HomepagePromoStripItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class HomepagePromoStripItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'admin.homepage_setting.promo_strip_item_enabled',
            ])
            ->add('position', IntegerType::class, [
                'required' => false,
                'label' => 'admin.homepage_setting.promo_strip_item_position',
                'help' => 'admin.homepage_setting.promo_strip_item_position_help',
                'attr' => ['min' => 0, 'max' => 999],
            ])
            ->add('content', TextareaType::class, [
                'required' => true,
                'label' => 'admin.homepage_setting.promo_strip_item_content',
                'help' => 'admin.homepage_setting.promo_strip_item_content_help',
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HomepagePromoStripItem::class,
            'label' => false,
            'translation_domain' => 'messages',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'homepage_promo_strip_item';
    }
}
