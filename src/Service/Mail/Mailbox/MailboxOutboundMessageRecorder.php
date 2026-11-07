<?php

namespace App\Service\Mail\Mailbox;

use App\Entity\MailboxAccount;
use App\Entity\MailboxMessage;
use App\Repository\MailboxAccountRepository;
use App\Repository\MailboxMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Stores outbound transactional emails in the admin mailbox (SENT),
 * so they appear in order/user conversations.
 */
final class MailboxOutboundMessageRecorder
{
    public function __construct(
        private readonly MailboxAccountRepository $accountRepository,
        private readonly MailboxMessageRepository $messageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function recordForOrder(
        string $fromEmail,
        string $fromName,
        string $to,
        string $subject,
        string $body,
        bool $isHtml,
        string $orderNumber,
        ?string $messageId = null,
    ): ?MailboxMessage {
        $orderNumber = trim($orderNumber);
        if ($orderNumber === '') {
            return null;
        }

        return $this->record(
            $fromEmail,
            $fromName,
            $to,
            $subject,
            $body,
            $isHtml,
            MailboxMessage::LINK_ORDER,
            $orderNumber,
            $messageId,
        );
    }

    public function record(
        string $fromEmail,
        string $fromName,
        string $to,
        string $subject,
        string $body,
        bool $isHtml,
        ?string $linkType = null,
        ?string $linkRef = null,
        ?string $messageId = null,
    ): ?MailboxMessage {
        $account = $this->resolveAccount($fromEmail);
        if (!$account instanceof MailboxAccount) {
            $this->logger->warning('Outbound mailbox message not stored: no active mailbox account.', [
                'from' => $fromEmail,
                'to' => $to,
                'subject' => $subject,
                'link_type' => $linkType,
                'link_ref' => $linkRef,
            ]);

            return null;
        }

        $previewSource = $isHtml
            ? trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : $body;
        $preview = trim(preg_replace('/\s+/u', ' ', $previewSource) ?? '');

        $message = (new MailboxMessage())
            ->setMailbox($account)
            ->setRemoteUid('sent-'.bin2hex(random_bytes(12)))
            ->setMessageId($this->normalizeMessageId($messageId, $account))
            ->setFromAddress($account->getEmail())
            ->setFromName($fromName !== '' ? $fromName : $account->getName())
            ->setToAddresses($to !== '' ? $to : null)
            ->setSubject($subject)
            ->setBodyPreview($preview !== '' ? $preview : null)
            ->setReceivedAt(new \DateTimeImmutable())
            ->setIsSeen(true)
            ->setIsNotified(true)
            ->setFolder(MailboxMessage::FOLDER_SENT)
            ->setMessageGroup(MailboxAccount::DEFAULT_MESSAGE_GROUP)
            ->setLinkType($linkType)
            ->setLinkRef($linkRef);

        if ($isHtml) {
            $message->setBodyHtml($body);
            $message->setBodyText($preview !== '' ? $preview : null);
        } else {
            $message->setBodyText($body);
        }

        $this->messageRepository->continueOrStartConversation($message);
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    private function resolveAccount(string $fromEmail): ?MailboxAccount
    {
        $fromEmail = strtolower(trim($fromEmail));
        if ($fromEmail !== '') {
            $matched = $this->accountRepository->findOneActiveByEmail($fromEmail);
            if ($matched instanceof MailboxAccount) {
                return $matched;
            }
        }

        $active = $this->accountRepository->findActive();

        return $active[0] ?? null;
    }

    private function normalizeMessageId(?string $messageId, MailboxAccount $account): string
    {
        $value = trim((string) $messageId);
        if ($value !== '') {
            if ($value[0] !== '<') {
                $value = '<'.$value;
            }
            if (!str_ends_with($value, '>')) {
                $value .= '>';
            }

            return $value;
        }

        $domain = 'localhost';
        $email = strtolower(trim($account->getEmail()));
        $at = strrpos($email, '@');
        if ($at !== false) {
            $host = preg_replace('/[^a-z0-9.-]/', '', substr($email, $at + 1)) ?? '';
            if ($host !== '') {
                $domain = $host;
            }
        }

        return '<'.bin2hex(random_bytes(16)).'@'.$domain.'>';
    }
}
