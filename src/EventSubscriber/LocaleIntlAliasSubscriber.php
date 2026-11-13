<?php

namespace App\EventSubscriber;

use App\Service\Intl\IntlLocaleMapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * URL / translator locale is "ua", but ICU/Intl only knows Ukrainian as "uk".
 *
 * Symfony LocaleListener and Form DateTime transformers use \Locale::getDefault().
 * Force ICU-safe default on every request stage before formatting runs.
 */
final class LocaleIntlAliasSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // After LocaleListener::onKernelRequest (16) and LocaleAwareListener (~15).
            KernelEvents::REQUEST => [
                ['onKernelRequest', 14],
                ['onKernelRequestLate', -1024],
            ],
            KernelEvents::CONTROLLER => ['onController', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        IntlLocaleMapper::applyDefault();
    }

    public function onKernelRequestLate(RequestEvent $event): void
    {
        IntlLocaleMapper::applyDefault();
    }

    public function onController(ControllerEvent $event): void
    {
        IntlLocaleMapper::applyDefault();
    }
}
