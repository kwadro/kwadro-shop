<?php

namespace App\Form\Type;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'auth.email',
                'attr' => ['class' => 'shop-auth-input'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'auth.password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'class' => 'shop-auth-input',
                    ],
                ],
                'second_options' => [
                    'label' => 'auth.repeat_password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'class' => 'shop-auth-input',
                    ],
                ],
                'constraints' => [
                    new NotBlank(message: 'auth.password_required'),
                    new Length(
                        min: 6,
                        minMessage: 'auth.password_min_length',
                    ),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'auth.agree_terms',
                'constraints' => [
                    new IsTrue(message: 'auth.agree_terms_required'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'messages',
        ]);
    }
}
