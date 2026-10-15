<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\Admin\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSuccessSubscriber implements EventSubscriberInterface
{
    public const SESSION_GREETING_SINCE = 'admin_mailbox_greeting_since';
    public const SESSION_GREETING_DONE = 'admin_mailbox_greeting_done';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminAuditLogger $adminAuditLogger,
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

        if (!\in_array(User::ROLE_SUPER_ADMIN, $user->getRoles(), true)) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();
        $previousLogin = $user->getLastAdminLoginAt();
        // Always set the key so poll knows a fresh admin login happened.
        $session->set(
            self::SESSION_GREETING_SINCE,
            $previousLogin instanceof \DateTimeImmutable
                ? $previousLogin->format(\DateTimeInterface::ATOM)
                : '',
        );
        $session->set(self::SESSION_GREETING_DONE, false);

        $user->setLastAdminLoginAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->adminAuditLogger->logLogin($user);

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('admin', ['_locale' => 'uk'])
        ));
    }
}
