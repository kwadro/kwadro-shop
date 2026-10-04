<?php

namespace App\EventSubscriber;

use App\Entity\RequestList;
use App\Entity\Site;
use App\Repository\SiteRepository;
use App\Service\GeoIp\UserCityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
            // After RedirectSubscriber (32) so we can store the redirect target.
            KernelEvents::RESPONSE => [
                ['onKernelResponse', 0],
                ['logVisitorIp', -10],
            ],
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

    public function logVisitorIp(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
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
            ->setPath($path)
            ->setUserAgent($request->headers->get('User-Agent'))
            ->setPathAfterRedirect($this->resolvePathAfterRedirect($event->getResponse()));

        $this->em->persist($entry);
        $this->em->flush();

        $request->attributes->set(self::LOGGED_ATTRIBUTE, true);
    }

    private function resolvePathAfterRedirect(Response $response): ?string
    {
        if ($response instanceof RedirectResponse) {
            $target = trim($response->getTargetUrl());

            return $target !== '' ? $target : null;
        }

        if ($response->isRedirect()) {
            $location = trim((string) $response->headers->get('Location', ''));

            return $location !== '' ? $location : null;
        }

        return null;
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
