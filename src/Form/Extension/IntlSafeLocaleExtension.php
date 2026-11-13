<?php

namespace App\Form\Extension;

use App\Service\Intl\IntlLocaleMapper;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Ensures date/time form types never use ICU-invalid locale "ua".
 */
final class IntlSafeLocaleExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [DateType::class, DateTimeType::class, TimeType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setNormalizer('locale', static function (Options $options, mixed $value): string {
            return IntlLocaleMapper::resolve(\is_string($value) ? $value : null);
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        // Form DateTimeToLocalizedStringTransformer reads \Locale::getDefault(), not options.locale.
        IntlLocaleMapper::applyDefault();
    }
}
