<?php

namespace App\EventSubscriber;

use App\Entity\RequestList;
use App\Entity\Site;
use App\Repository\SiteRepository;
use App\Service\GeoIp\UserCityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class UserCityCookieSubscriber implements EventSubscriberInterface
{
    private const LOGGED_ATTRIBUTE = '_user_ip_visit_logged';

    public function __construct(
        private readonly UserCityService $userCityService,
        private readonly EntityManagerInterface $em,
        private readonly SiteRepository $siteRepository,
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

        $request = $event->getRequest();
        $this->userCityService->resolveData($request);
        $this->logVisitorIp($request);
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

    private function logVisitorIp(Request $request): void
    {
        if ($request->attributes->getBoolean(self::LOGGED_ATTRIBUTE)) {
            return;
        }

        $path = $request->getPathInfo();
        if ($this->shouldSkipPath($path)) {
            return;
        }

        $ip = (string) ($request->getClientIp() ?? '');
        $site = $this->resolveSite($request);
        if ($site !== null && $site->isRequestIpIgnored($ip !== '' ? $ip : null)) {
            $request->attributes->set(self::LOGGED_ATTRIBUTE, true);

            return;
        }

        $entry = (new RequestList())
            ->setIp($ip !== '' ? $ip : null)
            ->setPath($path);

        $this->em->persist($entry);
        $this->em->flush();

        $request->attributes->set(self::LOGGED_ATTRIBUTE, true);
    }

    private function resolveSite(Request $request): ?Site
    {
        $site = $this->siteRepository->findOneBy(['domain' => $request->getHost()]);
        if ($site instanceof Site) {
            return $site;
        }

        $fallback = $this->siteRepository->find(1);

        return $fallback instanceof Site ? $fallback : null;
    }

    private function shouldSkipPath(string $path): bool
    {
        return str_starts_with($path, '/admin')
            || str_starts_with($path, '/_wdt')
            || str_starts_with($path, '/_profiler')
            || str_starts_with($path, '/_fragment')
            || str_starts_with($path, '/build/')
            || str_starts_with($path, '/bundles/')
            || str_starts_with($path, '/webhook');
    }
}
