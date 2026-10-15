<?php

namespace App\Controller\Admin;

use App\EventSubscriber\LoginSuccessSubscriber;
use App\Repository\MailboxMessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class MailboxNotificationController extends AbstractController
{
    public function __construct(
        private readonly MailboxMessageRepository $messageRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/admin/{_locale}/mailbox/notifications/poll', name: 'admin_mailbox_notifications_poll', methods: ['GET'], requirements: ['_locale' => 'uk|en'])]
    public function poll(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $greeting = null;

        // Greeting only after a fresh admin login (session key set in LoginSuccessSubscriber).
        if ($session->has(LoginSuccessSubscriber::SESSION_GREETING_SINCE)
            && !$session->get(LoginSuccessSubscriber::SESSION_GREETING_DONE, false)
        ) {
            $sinceRaw = $session->get(LoginSuccessSubscriber::SESSION_GREETING_SINCE);
            $since = null;
            if (\is_string($sinceRaw) && $sinceRaw !== '') {
                try {
                    $since = new \DateTimeImmutable($sinceRaw);
                } catch (\Exception) {
                    $since = null;
                }
            }

            $count = $this->messageRepository->countUnreadReceivedAfter($since);
            if ($count > 0) {
                $greeting = [
                    'type' => 'unread_after_login',
                    'count' => $count,
                    'title' => $this->translator->trans('admin.mailbox_notify.unread_title', [], 'messages'),
                    'body' => $this->translator->trans(
                        'admin.mailbox_notify.unread_body',
                        ['%count%' => $count],
                        'messages',
                    ),
                ];
            }

            $session->set(LoginSuccessSubscriber::SESSION_GREETING_DONE, true);
        }

        $messages = $this->messageRepository->findUnnotified(15);
        $payload = [];
        $ids = [];

        foreach ($messages as $message) {
            $ids[] = (int) $message->getId();
            $payload[] = [
                'id' => $message->getId(),
                'type' => 'new_mail',
                'title' => $this->translator->trans('admin.mailbox_notify.new_mail_title', [], 'messages'),
                'subject' => $message->getSubject(),
                'from' => $message->getFromDisplay(),
                'mailbox' => $message->getMailbox()?->getEmail(),
                'body' => trim(implode("\n", array_filter([
                    $message->getFromDisplay(),
                    $message->getSubject(),
                ]))),
                'receivedAt' => $message->getReceivedAt()->format(\DATE_ATOM),
            ];
        }

        if ($ids !== []) {
            $this->messageRepository->markNotifiedByIds($ids);
        }

        return $this->json([
            'unread' => $this->messageRepository->countUnread(),
            'greeting' => $greeting,
            'new' => $payload,
        ]);
    }
}
