<?php

namespace App\Service\Mail\Mailbox;

use App\Entity\MailboxAccount;
use App\Entity\MailboxMessage;
use App\Repository\MailboxMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class MailboxImapSyncService
{
    private const FETCH_LIMIT = 50;

    public function __construct(
        private readonly MailboxCredentialCipher $cipher,
        private readonly MailboxMessageRepository $messageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{imported: int, updated: int, error: string|null}
     */
    public function sync(MailboxAccount $account): array
    {
        if (!\function_exists('imap_open')) {
            $error = 'PHP extension ext-imap is required for mailbox sync. Install php-imap.';
            $account->setLastSyncError($error);
            $this->entityManager->flush();

            return ['imported' => 0, 'updated' => 0, 'error' => $error];
        }

        $imported = 0;
        $updated = 0;
        $mailbox = null;

        try {
            $password = $this->cipher->decrypt($account->getPasswordEncrypted());
            $mailbox = @imap_open(
                $this->buildMailboxPath($account),
                $account->getUsername(),
                $password,
                0,
                1,
                ['DISABLE_AUTHENTICATOR' => 'GSSAPI'],
            );

            if ($mailbox === false) {
                $error = imap_last_error() ?: 'IMAP connection failed.';
                $account->setLastSyncError($error);
                $account->setLastSyncedAt(new \DateTimeImmutable());
                $this->entityManager->flush();
                $this->logger->warning('Mailbox IMAP connect failed.', [
                    'mailbox_id' => $account->getId(),
                    'error' => $error,
                ]);

                return ['imported' => 0, 'updated' => 0, 'error' => $error];
            }

            $uids = imap_search($mailbox, 'ALL', \SE_UID) ?: [];
            if (\is_array($uids) && $uids !== []) {
                rsort($uids, \SORT_NUMERIC);
                $uids = \array_slice($uids, 0, self::FETCH_LIMIT);
            } else {
                $uids = [];
            }

            foreach ($uids as $uid) {
                $uid = (string) $uid;
                $overview = imap_fetch_overview($mailbox, $uid, \FT_UID);
                $header = $overview[0] ?? null;
                if ($header === null) {
                    continue;
                }

                $existing = $this->messageRepository->findOneByMailboxAndUid($account, $uid);
                $isNew = $existing === null;
                $message = $existing ?? (new MailboxMessage())->setMailbox($account)->setRemoteUid($uid);

                $from = $this->parseAddress((string) ($header->from ?? ''));
                $message
                    ->setMessageId(isset($header->message_id) ? (string) $header->message_id : null)
                    ->setFromAddress($from['email'])
                    ->setFromName($from['name'])
                    ->setToAddresses(isset($header->to) ? (string) $header->to : null)
                    ->setSubject($this->decodeMime((string) ($header->subject ?? '(без теми)')))
                    ->setReceivedAt($this->resolveDate($header))
                    ->setIsSeen(!empty($header->seen))
                    ->setHasAttachments($this->detectAttachments($mailbox, $uid))
                    ->setFolder('INBOX');

                $structure = @imap_fetchstructure($mailbox, (int) $uid, \FT_UID);
                [$text, $html] = $this->extractBodies($mailbox, $uid, $structure);
                $message->setBodyText($text);
                $message->setBodyHtml($html);
                $previewSource = $text !== null && $text !== '' ? $text : strip_tags((string) $html);
                $message->setBodyPreview($previewSource !== '' ? $previewSource : null);

                if ($isNew) {
                    $this->entityManager->persist($message);
                    ++$imported;
                } else {
                    ++$updated;
                }
            }

            $account->setLastSyncError(null);
            $account->setLastSyncedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            return ['imported' => $imported, 'updated' => $updated, 'error' => null];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $account->setLastSyncError($error);
            $account->setLastSyncedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->logger->error('Mailbox IMAP sync failed.', [
                'mailbox_id' => $account->getId(),
                'error' => $error,
            ]);

            return ['imported' => $imported, 'updated' => $updated, 'error' => $error];
        } finally {
            if (\is_resource($mailbox) || $mailbox instanceof \IMAP\Connection) {
                imap_close($mailbox);
            }
        }
    }

    private function buildMailboxPath(MailboxAccount $account): string
    {
        $flags = match ($account->getImapEncryption()) {
            'ssl' => '/imap/ssl/novalidate-cert',
            'tls' => '/imap/tls/novalidate-cert',
            default => '/imap/notls',
        };

        return sprintf('{%s:%d%s}INBOX', $account->getImapHost(), $account->getImapPort(), $flags);
    }

    /** @return array{email: string, name: string|null} */
    private function parseAddress(string $raw): array
    {
        $decoded = $this->decodeMime($raw);
        if (preg_match('/^(.*?)<([^>]+)>$/u', $decoded, $m)) {
            $name = trim(trim($m[1]), '"\'');

            return [
                'email' => trim($m[2]),
                'name' => $name !== '' ? $name : null,
            ];
        }

        return ['email' => trim($decoded), 'name' => null];
    }

    private function decodeMime(string $value): string
    {
        $decoded = imap_mime_header_decode($value);
        if (!\is_array($decoded) || $decoded === []) {
            return $value;
        }

        $parts = [];
        foreach ($decoded as $part) {
            $charset = $part->charset ?? 'default';
            $text = (string) ($part->text ?? '');
            if ($charset !== 'default' && $charset !== '' && strtoupper($charset) !== 'UTF-8') {
                $converted = @iconv($charset, 'UTF-8//IGNORE', $text);
                $text = $converted !== false ? $converted : $text;
            }
            $parts[] = $text;
        }

        return implode('', $parts);
    }

    private function resolveDate(object $header): \DateTimeImmutable
    {
        $raw = (string) ($header->date ?? '');
        try {
            return new \DateTimeImmutable($raw !== '' ? $raw : 'now');
        } catch (\Exception) {
            return new \DateTimeImmutable();
        }
    }

    private function detectAttachments($mailbox, string $uid): bool
    {
        $structure = @imap_fetchstructure($mailbox, (int) $uid, \FT_UID);
        if ($structure === false || !isset($structure->parts) || !\is_array($structure->parts)) {
            return false;
        }

        foreach ($structure->parts as $part) {
            $disposition = strtolower((string) ($part->disposition ?? ''));
            if ($disposition === 'attachment') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function extractBodies($mailbox, string $uid, mixed $structure): array
    {
        if ($structure === false || $structure === null) {
            $raw = imap_body($mailbox, (int) $uid, \FT_UID | \FT_PEEK) ?: '';

            return [$raw !== '' ? $raw : null, null];
        }

        $text = null;
        $html = null;

        if (!isset($structure->parts) || !\is_array($structure->parts)) {
            $raw = $this->decodePart(
                imap_body($mailbox, (int) $uid, \FT_UID | \FT_PEEK) ?: '',
                (int) ($structure->encoding ?? 0),
                $this->partCharset($structure),
            );
            if ((int) ($structure->subtype ?? 0) === 0 && strtoupper((string) ($structure->subtype ?? 'PLAIN')) === 'HTML') {
                $html = $raw;
            } else {
                $text = $raw;
            }

            return [$text, $html];
        }

        foreach ($structure->parts as $index => $part) {
            $partNo = (string) ($index + 1);
            $subtype = strtoupper((string) ($part->subtype ?? 'PLAIN'));
            $body = imap_fetchbody($mailbox, (int) $uid, $partNo, \FT_UID | \FT_PEEK) ?: '';
            $decoded = $this->decodePart($body, (int) ($part->encoding ?? 0), $this->partCharset($part));
            if ($subtype === 'PLAIN' && ($text === null || $text === '')) {
                $text = $decoded;
            }
            if ($subtype === 'HTML' && ($html === null || $html === '')) {
                $html = $decoded;
            }
        }

        return [$text, $html];
    }

    private function decodePart(string $body, int $encoding, string $charset): string
    {
        $decoded = match ($encoding) {
            3 => base64_decode($body, true) ?: $body, // ENCBASE64
            4 => quoted_printable_decode($body), // ENCQUOTEDPRINTABLE
            default => $body,
        };

        if ($charset !== '' && strtoupper($charset) !== 'UTF-8') {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $decoded);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $decoded;
    }

    private function partCharset(object $part): string
    {
        if (!isset($part->parameters) || !\is_array($part->parameters)) {
            return 'UTF-8';
        }

        foreach ($part->parameters as $param) {
            if (strtoupper((string) ($param->attribute ?? '')) === 'CHARSET') {
                return (string) ($param->value ?? 'UTF-8');
            }
        }

        return 'UTF-8';
    }
}
