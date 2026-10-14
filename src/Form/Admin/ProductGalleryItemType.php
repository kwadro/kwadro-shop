<?php

namespace App\Form\Admin;

use App\Form\Model\ProductGalleryItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class ProductGalleryItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('path', HiddenType::class, [
                'required' => false,
            ])
            ->add('file', FileType::class, [
                'required' => false,
                'label' => 'Image',
                'constraints' => [
                    new File([
                        'maxSize' => '12M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                        ],
                    ]),
                ],
            ])
            ->add('alt', TextType::class, [
                'required' => false,
                'label' => 'Alt text',
                'empty_data' => '',
            ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $data = $form->getData();
        $path = $data instanceof ProductGalleryItem ? (string) ($data->path ?? '') : '';
        $view->vars['preview_url'] = $path !== ''
            ? '/uploads/products/'.ltrim($path, '/')
            : null;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductGalleryItem::class,
            'label' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'product_gallery_item';
    }
}
