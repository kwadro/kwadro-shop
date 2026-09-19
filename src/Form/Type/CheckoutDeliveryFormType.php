<?php

namespace App\Form\Type;

use App\Dto\CheckoutDeliveryData;
use App\Entity\ShopDeliveryMethod;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CheckoutDeliveryFormType extends AbstractType
{
    private const REQUIRED_MESSAGE = 'shop.validation.required';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $enabledMethods */
        $enabledMethods = $options['enabled_delivery_methods'];
        $choices = $this->buildChoices($enabledMethods);
        $defaultMethod = $choices !== [] ? array_values($choices)[0] : ShopDeliveryMethod::NovaPoshtaBranch;

        $builder
            ->add('deliveryMethod', ChoiceType::class, [
                'label' => 'shop.checkout.delivery_method',
                'choices' => $choices,
                'expanded' => true,
                'multiple' => false,
                'empty_data' => $defaultMethod,
                'attr' => ['class' => 'shop-checkout__delivery-options'],
            ])
        ;

        if (in_array(ShopDeliveryMethod::Courier, $enabledMethods, true)) {
            $builder->add('courierAddress', TextareaType::class, [
                'label' => 'shop.checkout.courier_address',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'class' => 'shop-checkout__input',
                    'rows' => 3,
                    'placeholder' => 'shop.checkout.courier_address_placeholder',
                ],
            ]);
        }

        $builder
            ->add('npCityRef', HiddenType::class, ['required' => false, 'empty_data' => ''])
            ->add('npCityName', HiddenType::class, ['required' => false, 'empty_data' => ''])
            ->add('npWarehouseRef', HiddenType::class, ['required' => false, 'empty_data' => ''])
            ->add('npWarehouseName', HiddenType::class, ['required' => false, 'empty_data' => ''])
            ->add('deliveryCost', HiddenType::class, ['required' => false, 'empty_data' => '0'])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($choices): void {
            $data = $event->getData();
            if (!$data instanceof CheckoutDeliveryData) {
                return;
            }

            $available = array_values($choices);
            if ($available !== [] && !in_array($data->deliveryMethod, $available, true)) {
                $data->deliveryMethod = $available[0];
            }

            if (!in_array(ShopDeliveryMethod::Courier, $available, true)) {
                $data->courierAddress = null;
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) use ($choices): void {
            /** @var CheckoutDeliveryData $data */
            $data = $event->getData();
            $form = $event->getForm();
            $available = array_values($choices);

            if ($available !== [] && !in_array($data->deliveryMethod ?? '', $available, true)) {
                $form->get('deliveryMethod')->addError(new \Symfony\Component\Form\FormError('shop.checkout.delivery_method_unavailable'));

                return;
            }

            if (
                ($data->deliveryMethod ?? '') === ShopDeliveryMethod::Courier
                && $form->has('courierAddress')
                && trim((string) $data->courierAddress) === ''
            ) {
                $form->get('courierAddress')->addError(new \Symfony\Component\Form\FormError(self::REQUIRED_MESSAGE));
            }

            if (in_array($data->deliveryMethod ?? '', ['np_branch', 'np_postomat'], true)) {
                $data->courierAddress = null;

                if (!$data->npCityRef) {
                    $form->get('npCityRef')->addError(new \Symfony\Component\Form\FormError(self::REQUIRED_MESSAGE));
                }

                if (!$data->npWarehouseRef) {
                    $form->get('npWarehouseRef')->addError(new \Symfony\Component\Form\FormError(self::REQUIRED_MESSAGE));
                }
            } else {
                $data->npCityRef = null;
                $data->npCityName = null;
                $data->npWarehouseRef = null;
                $data->npWarehouseName = null;
            }
        });
    }

    /** @param list<string> $enabledMethods */
    /** @return array<string, string> */
    private function buildChoices(array $enabledMethods): array
    {
        $allChoices = [
            'shop.checkout.delivery_courier' => ShopDeliveryMethod::Courier,
            'shop.checkout.delivery_np_branch' => ShopDeliveryMethod::NovaPoshtaBranch,
            'shop.checkout.delivery_np_postomat' => ShopDeliveryMethod::NovaPoshtaPostomat,
        ];

        return array_filter(
            $allChoices,
            static fn (string $value): bool => in_array($value, $enabledMethods, true),
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CheckoutDeliveryData::class,
            'translation_domain' => 'messages',
            'enabled_delivery_methods' => ShopDeliveryMethod::all(),
        ]);

        $resolver->setAllowedTypes('enabled_delivery_methods', 'array');
    }
}
