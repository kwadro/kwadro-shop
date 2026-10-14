<?php

namespace App\Controller\Admin;

use App\Entity\MailboxMessage;
use App\Service\Mail\Mailbox\MailboxAttachmentStorage;
use App\Service\Mail\Mailbox\MailboxImapSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class MailboxMessageAttachmentController extends AbstractController
{
    public function __construct(
        private readonly MailboxAttachmentStorage $attachmentStorage,
        private readonly MailboxImapSyncService $syncService,
    ) {
    }

    #[Route(
        '/admin/{_locale}/mailbox-message/{id}/attachment/{stored}',
        name: 'admin_mailbox_message_attachment',
        requirements: [
            '_locale' => 'uk|en',
            'id' => '\d+',
            'stored' => '[^/]+',
        ],
        methods: ['GET'],
    )]
    public function __invoke(MailboxMessage $message, string $stored): Response
    {
        $stored = basename(rawurldecode($stored));

        if ($message->getAttachments() === [] && $message->hasAttachments()) {
            $this->syncService->ensureAttachments($message);
        }

        $meta = null;
        foreach ($message->getAttachments() as $item) {
            if (($item['stored'] ?? '') === $stored) {
                $meta = $item;
                break;
            }
        }
        if ($meta === null) {
            throw new NotFoundHttpException('Attachment not found.');
        }

        $path = $this->attachmentStorage->absolutePath($message, $stored);
        if ($path === null || !is_file($path)) {
            throw new NotFoundHttpException('Attachment file is missing on the server.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $meta['mime'] ?? 'application/octet-stream');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            (string) ($meta['name'] ?? $stored),
        );

        return $response;
    }
}
