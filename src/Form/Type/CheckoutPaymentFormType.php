<?php

namespace App\Form\Type;

use App\Dto\CheckoutPaymentData;
use App\Entity\ShopPaymentMethod;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CheckoutPaymentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $enabledMethods */
        $enabledMethods = $options['enabled_payment_methods'];
        /** @var list<string>|null $allowedMethods */
        $allowedMethods = $options['allowed_payment_methods'];
        $choices = $this->buildChoices($enabledMethods);
        $effectiveAllowedMethods = $allowedMethods ?? $enabledMethods;

        $builder->add('paymentMethod', ChoiceType::class, [
            'label' => 'shop.checkout.payment_method',
            'choices' => $choices,
            'expanded' => true,
            'multiple' => false,
            'empty_data' => $choices !== [] ? array_values($choices)[0] : null,
            'constraints' => [new NotBlank()],
            'attr' => ['class' => 'shop-checkout__payment-options'],
        ]);

        $builder->add('doNotCall', CheckboxType::class, [
            'label' => 'shop.checkout.do_not_call',
            'required' => false,
            'false_values' => [null, '', '0', 'false', false],
            'row_attr' => ['class' => 'shop-checkout__checkbox-row'],
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($choices, $effectiveAllowedMethods): void {
            $data = $event->getData();
            if (!$data instanceof CheckoutPaymentData) {
                return;
            }

            $available = array_values($choices);
            $allowed = array_values(array_intersect($available, $effectiveAllowedMethods));
            $preferred = $allowed[0] ?? $available[0] ?? null;

            if ($preferred !== null && !in_array($data->paymentMethod, $allowed, true)) {
                $data->paymentMethod = $preferred;
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) use ($choices, $effectiveAllowedMethods): void {
            $data = $event->getData();
            if (!$data instanceof CheckoutPaymentData) {
                return;
            }

            $available = array_values($choices);
            $allowed = array_values(array_intersect($available, $effectiveAllowedMethods));

            if ($allowed !== [] && !in_array((string) $data->paymentMethod, $allowed, true)) {
                $event->getForm()->get('paymentMethod')->addError(
                    new FormError('shop.checkout.payment_method_unavailable'),
                );
            }
        });
    }

    /** @param list<string> $enabledMethods */
    /** @return array<string, string> */
    private function buildChoices(array $enabledMethods): array
    {
        $allChoices = [
            'shop.checkout.payment_on_delivery' => ShopPaymentMethod::OnDelivery,
            'shop.checkout.payment_privatbank' => ShopPaymentMethod::Privatbank,
            'shop.checkout.payment_monobank' => ShopPaymentMethod::Monobank,
        ];

        return array_filter(
            $allChoices,
            static fn (string $value): bool => in_array($value, $enabledMethods, true),
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CheckoutPaymentData::class,
            'translation_domain' => 'messages',
            'enabled_payment_methods' => ShopPaymentMethod::all(),
            'allowed_payment_methods' => null,
        ]);

        $resolver->setAllowedTypes('enabled_payment_methods', 'array');
        $resolver->setAllowedTypes('allowed_payment_methods', ['null', 'array']);
    }
}
