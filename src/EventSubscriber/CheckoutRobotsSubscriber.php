<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CheckoutRobotsSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    private const NOINDEX_ROUTES = [
        'shop_checkout',
        'shop_checkout_start',
        'shop_checkout_success',
        'shop_checkout_root_redirect',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route', '');
        if (!in_array($route, self::NOINDEX_ROUTES, true)) {
            return;
        }

        $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
    }
}
