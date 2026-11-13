<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * URL / translator locale is "ua", but ICU/Intl only knows Ukrainian as "uk".
 * EasyAdmin date/number formatters use \Locale::getDefault() and crash on "ua".
 */
final class LocaleIntlAliasSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // After Symfony LocaleListener::onKernelRequest (priority 16).
        return [KernelEvents::REQUEST => ['onKernelRequest', 15]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($event->getRequest()->getLocale() === 'ua' && \extension_loaded('intl')) {
            \Locale::setDefault('uk');
        }
    }
}
