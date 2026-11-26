<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Severs window.opener / window.open() browsing-context links so pages stay eligible for bfcache.
 *
 * Without COOP, Chrome reports RelatedActiveContentsExist ("The page was opened using window.open()...").
 */
class CrossOriginOpenerPolicySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onKernelResponse', -100]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$this->isHtmlResponse($request, $response)) {
            return;
        }

        // same-origin puts this document in its own browsing context group when opened via
        // window.open(), so the opener cannot keep a live reference (bfcache blocker).
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
    }

    private function isHtmlResponse(Request $request, Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && !str_contains($contentType, 'text/html')) {
            return false;
        }

        // Prefer explicit HTML; also cover responses that omit Content-Type on HTML routes.
        if ($contentType === '' && !$request->isMethodSafe()) {
            return false;
        }

        return true;
    }
}
