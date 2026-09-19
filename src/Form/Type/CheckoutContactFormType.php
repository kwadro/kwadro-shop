<?php

namespace App\Form\Type;

use App\Dto\CheckoutContactData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class CheckoutContactFormType extends AbstractType
{
    private const REQUIRED_MESSAGE = 'shop.validation.required';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customerName', TextType::class, [
                'label' => 'shop.checkout.customer_name',
                'empty_data' => '',
                'constraints' => [new NotBlank(message: self::REQUIRED_MESSAGE)],
                'attr' => ['class' => 'shop-checkout__input'],
            ])
        ;

        if ($options['show_email_field']) {
            $builder->add('customerEmail', EmailType::class, [
                'label' => 'shop.checkout.customer_email',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(message: self::REQUIRED_MESSAGE),
                    new Email(),
                ],
                'attr' => ['class' => 'shop-checkout__input'],
            ]);
        }

        $builder
            ->add('customerPhone', TelType::class, [
                'label' => 'shop.checkout.customer_phone',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(message: self::REQUIRED_MESSAGE),
                    new Regex(pattern: '/^\+?[\d\s\-()]{10,20}$/'),
                ],
                'attr' => [
                    'class' => 'shop-checkout__input',
                    'placeholder' => '+380...',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CheckoutContactData::class,
            'translation_domain' => 'messages',
            'show_email_field' => true,
        ]);

        $resolver->setAllowedTypes('show_email_field', 'bool');
    }
}
