<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\Cart\CartStorageService;
use App\Service\Cart\VisitorIdResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class CartLogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CartStorageService $cartStorage,
        private readonly VisitorIdResolver $visitorIdResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        if (!$user instanceof User) {
            return;
        }

        $newVisitorId = $this->visitorIdResolver->generateNext();
        $this->cartStorage->transferCustomerCartToGuest($user, $newVisitorId);

        $response = $event->getResponse();
        if ($response === null) {
            return;
        }

        $request = $event->getRequest();
        $request->attributes->set(VisitorIdResolver::REQUEST_ATTRIBUTE, $newVisitorId);
        $this->visitorIdResolver->attachCookie($response, $request, $newVisitorId);
    }
}
