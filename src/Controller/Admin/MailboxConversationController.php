<?php

namespace App\Controller\Admin;

use App\Entity\MailboxMessage;
use App\Repository\MailboxMessageRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[AdminRoute('/mailbox-conversation', name: 'mailbox_conversation')]
class MailboxConversationController extends AbstractController
{
    public function __construct(
        private readonly MailboxMessageRepository $messageRepository,
        private readonly TranslatorInterface $translator,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    #[AdminRoute('', name: 'index')]
    public function index(): Response
    {
        $conversations = [];
        foreach ($this->messageRepository->findConversationSummaries() as $summary) {
            $summary['linkLabel'] = $this->linkLabel($summary['linkType'], $summary['linkRef']);
            $summary['url'] = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction('show')
                ->set('conversationId', $summary['conversationId'])
                ->generateUrl();
            $conversations[] = $summary;
        }

        return $this->render('admin/mailbox/conversations.html.twig', [
            'page_title' => $this->translator->trans('admin.mailbox_conversation.title', [], 'messages'),
            'conversations' => $conversations,
            'messages' => null,
            'conversation_id' => null,
        ]);
    }

    #[AdminRoute('/{conversationId}', name: 'show', options: ['requirements' => ['conversationId' => '[0-9a-fA-F-]{36}']])]
    public function show(string $conversationId): Response
    {
        $stored = $this->messageRepository->findByConversationId($conversationId);
        if ($stored === []) {
            throw $this->createNotFoundException();
        }

        $messages = [];
        foreach ($stored as $message) {
            $controller = $message->getFolder() === MailboxMessage::FOLDER_SENT
                ? MailboxSentMessageCrudController::class
                : MailboxMessageCrudController::class;
            $messages[] = [
                'receivedAt' => $message->getReceivedAt(),
                'direction' => $message->getFolder() === MailboxMessage::FOLDER_SENT
                    ? $this->translator->trans('admin.mailbox_conversation.direction_out', [], 'messages')
                    : $this->translator->trans('admin.mailbox_conversation.direction_in', [], 'messages'),
                'who' => $message->getFolder() === MailboxMessage::FOLDER_SENT
                    ? trim((string) $message->getToAddresses())
                    : $message->getFromDisplay(),
                'subject' => $message->getSubject(),
                'linkLabel' => $this->linkLabel($message->getLinkType(), $message->getLinkRef()),
                'url' => $this->adminUrlGenerator
                    ->setController($controller)
                    ->setAction(Action::DETAIL)
                    ->setEntityId($message->getId())
                    ->generateUrl(),
            ];
        }

        return $this->render('admin/mailbox/conversations.html.twig', [
            'page_title' => $this->translator->trans('admin.mailbox_conversation.detail_title', [], 'messages'),
            'conversations' => null,
            'messages' => $messages,
            'conversation_id' => $conversationId,
            'dialog_url' => $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction('dialog')
                ->unset('entityId')
                ->set('conversationId', $conversationId)
                ->generateUrl(),
            'back_url' => $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction('index')
                ->unset('entityId')
                ->unset('conversationId')
                ->generateUrl(),
        ]);
    }

    #[AdminRoute('/{conversationId}/dialog', name: 'dialog', options: ['requirements' => ['conversationId' => '[0-9a-fA-F-]{36}']])]
    public function dialog(string $conversationId): Response
    {
        $stored = $this->messageRepository->findByConversationId($conversationId);
        if ($stored === []) {
            throw $this->createNotFoundException();
        }

        $messages = [];
        foreach ($stored as $message) {
            $outgoing = $message->getFolder() === MailboxMessage::FOLDER_SENT;
            $messages[] = [
                'receivedAt' => $message->getReceivedAt(),
                'outgoing' => $outgoing,
                'direction' => $outgoing
                    ? $this->translator->trans('admin.mailbox_conversation.direction_out', [], 'messages')
                    : $this->translator->trans('admin.mailbox_conversation.direction_in', [], 'messages'),
                'who' => $outgoing
                    ? trim((string) $message->getToAddresses())
                    : $message->getFromDisplay(),
                'subject' => $message->getSubject(),
                'bodyHtml' => $this->messageBodyHtml($message),
            ];
        }

        return new Response($this->renderView('admin/mailbox/conversation_dialog.html.twig', [
            'messages' => $messages,
        ]), Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "default-src 'none'; img-src * data: blob:; style-src 'unsafe-inline'; font-src * data:; frame-ancestors 'self';",
        ]);
    }

    private function messageBodyHtml(MailboxMessage $message): string
    {
        $html = trim((string) $message->getBodyHtml());
        if ($html !== '') {
            if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches) === 1) {
                $html = $matches[1];
            }
            $html = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $html) ?? $html;
            $html = preg_replace('/<\/?(?:html|body)\b[^>]*>/i', '', $html) ?? $html;
            $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
            $html = preg_replace('/<link\b[^>]*>/is', '', $html) ?? $html;
            $html = preg_replace('/<base\b[^>]*>/is', '', $html) ?? $html;
            $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
            $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html) ?? $html;
            $html = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/iu', '', $html) ?? $html;
            $html = preg_replace('/\son\w+\s*=\s*[^\s>]+/iu', '', $html) ?? $html;
            $html = preg_replace('/javascript\s*:/iu', '', $html) ?? $html;

            return trim($html);
        }

        $text = trim((string) ($message->getBodyText() ?? ''));
        if ($text === '') {
            $text = trim((string) ($message->getBodyPreview() ?? ''));
        }
        if ($text === '') {
            return '';
        }

        return '<p>'.nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</p>';
    }

    private function linkLabel(?string $type, ?string $ref): string
    {
        if ($type === null || $ref === null || $ref === '') {
            return '';
        }

        $key = $type === MailboxMessage::LINK_ORDER
            ? 'admin.mailbox_message.link_order'
            : 'admin.mailbox_message.link_user';

        return $this->translator->trans($key, ['%ref%' => $ref], 'messages');
    }
}
