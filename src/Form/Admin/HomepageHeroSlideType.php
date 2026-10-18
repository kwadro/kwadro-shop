<?php

namespace App\Form\Admin;

use App\Entity\HomepageHeroSlide;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class HomepageHeroSlideType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'admin.homepage_setting.hero_slide_enabled',
            ])
            ->add('position', IntegerType::class, [
                'required' => false,
                'label' => 'admin.homepage_setting.hero_slide_position',
                'attr' => ['min' => 0, 'max' => 999],
                'help' => 'admin.homepage_setting.hero_slide_position_help',
            ])
            ->add('brand', TextType::class, [
                'required' => false,
                'label' => 'admin.homepage_setting.hero_brand',
                'help' => 'admin.homepage_setting.hero_brand_help',
            ])
            ->add('title', TextType::class, [
                'required' => false,
                'label' => 'admin.homepage_setting.hero_title',
                'help' => 'admin.homepage_setting.hero_title_help',
            ])
            ->add('lead', TextareaType::class, [
                'required' => false,
                'label' => 'admin.homepage_setting.hero_lead',
                'help' => 'admin.homepage_setting.hero_lead_help',
                'attr' => ['rows' => 3],
            ])
            ->add('imageFile', FileType::class, [
                'required' => false,
                'mapped' => true,
                'label' => 'admin.homepage_setting.hero_image',
                'help' => 'admin.homepage_setting.hero_image_help',
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
            ->add('removeImage', CheckboxType::class, [
                'required' => false,
                'mapped' => true,
                'label' => 'admin.homepage_setting.hero_image_remove',
                'attr' => [
                    'data-homepage-hero-remove' => '1',
                ],
            ])
            ->add('imagePosition', IntegerType::class, [
                'required' => false,
                'label' => 'admin.homepage_setting.hero_image_position',
                'help' => 'admin.homepage_setting.hero_image_position_help',
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                    'data-homepage-hero-image-position' => '1',
                ],
                'empty_data' => (string) HomepageHeroSlide::DEFAULT_IMAGE_POSITION,
            ])
            ->add('primaryCtaLabel', TextType::class, [
                'required' => false,
                'label' => 'admin.homepage_setting.hero_primary_cta_label',
            ])
            ->add('primaryCtaUrl', TextType::class, [
                'required' => false,
                'label' => 'admin.homepage_setting.hero_primary_cta_url',
                'help' => 'admin.homepage_setting.hero_cta_url_help',
            ])
            ->add('secondaryCtaLabel', TextType::class, [
                'required' => false,
                'label' => 'admin.homepage_setting.hero_secondary_cta_label',
            ])
            ->add('secondaryCtaUrl', TextType::class, [
                'required' => false,
                'label' => 'admin.homepage_setting.hero_secondary_cta_url',
                'help' => 'admin.homepage_setting.hero_cta_url_help',
            ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $data = $form->getData();
        $image = $data instanceof HomepageHeroSlide ? (string) ($data->getImage() ?? '') : '';
        $view->vars['preview_url'] = $image !== ''
            ? '/uploads/images/'.ltrim($image, '/')
            : null;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HomepageHeroSlide::class,
            'label' => false,
            'translation_domain' => 'messages',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'homepage_hero_slide';
    }
}
