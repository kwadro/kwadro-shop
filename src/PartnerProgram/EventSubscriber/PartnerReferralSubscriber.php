<?php

namespace App\PartnerProgram\EventSubscriber;

use App\PartnerProgram\Service\PartnerProgramLogger;
use App\PartnerProgram\Service\PartnerReferralTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class PartnerReferralSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PartnerReferralTracker $partnerReferralTracker,
        private readonly PartnerProgramLogger $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Before RedirectSubscriber (32) so referral query/path are handled first.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 40],
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

        $this->logger->info('onKernelRequest', [
            'path' => $path,
            'query' => $request->getQueryString(),
        ]);

        $response = $this->partnerReferralTracker->trackAndRedirect($request);
        if ($response !== null) {
            $event->setResponse($response);
        }
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
