<?php

namespace App\Form\Extension;

use App\Service\Intl\IntlLocaleMapper;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * Ensures date/time form types never use ICU-invalid locale "ua".
 *
 * Symfony Form 7.4 Date/DateTime/Time types no longer expose a "locale" option;
 * transformers read \Locale::getDefault() instead.
 */
final class IntlSafeLocaleExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [DateType::class, DateTimeType::class, TimeType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        IntlLocaleMapper::applyDefault();
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        IntlLocaleMapper::applyDefault();
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        IntlLocaleMapper::applyDefault();
    }
}
