<?php

namespace App\EventSubscriber;

use App\Repository\BlockedIpRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class BlockedIpSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BlockedIpRepository $blockedIpRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Run early, before visit logging / city cookie.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if ($this->shouldSkipPath($path)) {
            return;
        }

        $ip = trim((string) ($request->getClientIp() ?? ''));
        if ($ip === '' || !$this->blockedIpRepository->isIpBlocked($ip)) {
            return;
        }

        $event->setResponse(new Response(
            'Access denied.',
            Response::HTTP_FORBIDDEN,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        ));
    }

    private function shouldSkipPath(string $path): bool
    {
        // Keep admin reachable so blocked IPs can be managed.
        if (str_starts_with($path, '/admin')) {
            return true;
        }

        if (str_starts_with($path, '/_wdt') || str_starts_with($path, '/_profiler')) {
            return true;
        }

        return false;
    }
}
