<?php

namespace App\EventSubscriber;

use App\Repository\RedirectRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RedirectRepository $redirectRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Before routing / controllers, after early security bits.
            KernelEvents::REQUEST => ['onKernelRequest', 32],
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

        $redirect = $this->redirectRepository->findReadyByFromPath($path);
        if ($redirect === null) {
            return;
        }

        $target = (string) $redirect->getToPath();
        if ($target === '' || $target === $path) {
            return;
        }

        $query = $request->getQueryString();
        if ($query !== null && $query !== '' && !str_contains($target, '?') && !preg_match('#^https?://#i', $target)) {
            $target .= '?'.$query;
        }

        $event->setResponse(new RedirectResponse($target, $redirect->getStatusCode()));
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
