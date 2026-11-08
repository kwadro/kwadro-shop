<?php

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductImportParamValuePairType extends AbstractType
{
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
            'translation_domain' => 'messages',
        ]);
    }
}
