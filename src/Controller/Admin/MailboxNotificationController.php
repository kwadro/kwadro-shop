<?php

namespace App\Controller\Admin;

use App\Repository\MailboxMessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class MailboxNotificationController extends AbstractController
{
    public function __construct(
        private readonly MailboxMessageRepository $messageRepository,
    ) {
    }

    #[Route('/admin/{_locale}/mailbox/notifications/poll', name: 'admin_mailbox_notifications_poll', methods: ['GET'], requirements: ['_locale' => 'uk|en'])]
    public function poll(): JsonResponse
    {
        $messages = $this->messageRepository->findUnnotified(15);
        $payload = [];
        $ids = [];

        foreach ($messages as $message) {
            $ids[] = (int) $message->getId();
            $payload[] = [
                'id' => $message->getId(),
                'subject' => $message->getSubject(),
                'from' => $message->getFromDisplay(),
                'mailbox' => $message->getMailbox()?->getEmail(),
                'receivedAt' => $message->getReceivedAt()->format(\DATE_ATOM),
            ];
        }

        if ($ids !== []) {
            $this->messageRepository->markNotifiedByIds($ids);
        }

        return $this->json([
            'unread' => $this->messageRepository->countUnread(),
            'new' => $payload,
        ]);
    }
}
