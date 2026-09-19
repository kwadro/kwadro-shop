<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\Cart\CartStorageService;
use App\Service\Cart\VisitorIdResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class CartLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CartStorageService $cartStorage,
        private readonly VisitorIdResolver $visitorIdResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $visitorId = (string) $event->getRequest()->cookies->get(VisitorIdResolver::COOKIE_NAME, '');
        if ($visitorId === '') {
            $this->cartStorage->restoreCustomerCartOnLogin('', $user);

            return;
        }

        $this->cartStorage->restoreCustomerCartOnLogin($visitorId, $user);
    }
}
