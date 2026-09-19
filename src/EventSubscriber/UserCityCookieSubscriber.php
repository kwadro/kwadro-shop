<?php

namespace App\EventSubscriber;

use App\Service\GeoIp\UserCityService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class UserCityCookieSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UserCityService $userCityService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 90],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->userCityService->resolveData($event->getRequest());
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->userCityService->shouldAttachCookie($request)) {
            return;
        }

        $this->userCityService->attachCookie(
            $event->getResponse(),
            $request,
            $this->userCityService->getCityDataForCookie($request),
        );
    }
}
