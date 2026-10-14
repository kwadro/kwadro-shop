<?php

namespace App\Service\Mail\Mailbox;

use App\Entity\MailboxAccount;
use App\Entity\MailboxMessage;
use App\Repository\MailboxMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class MailboxImapSyncService
{
    private const FETCH_LIMIT = 200;

    public function __construct(
        private readonly MailboxCredentialCipher $cipher,
        private readonly MailboxMessageRepository $messageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailboxAttachmentStorage $attachmentStorage,
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

            $afterUid = $this->resolveLastSyncedUid($account);
            $uids = $this->searchUidsAfter($mailbox, $account, $afterUid);
            $maxSeenUid = $afterUid;

            foreach ($uids as $uid) {
                $uidInt = (int) $uid;
                $uid = (string) $uid;
                if ($uidInt > $maxSeenUid) {
                    $maxSeenUid = $uidInt;
                }

                // Already synced — never re-fetch or update.
                if ($this->messageRepository->findOneByMailboxAndUid($account, $uid) !== null) {
                    continue;
                }

                $overview = imap_fetch_overview($mailbox, $uid, \FT_UID);
                $header = $overview[0] ?? null;
                if ($header === null) {
                    continue;
                }

                $from = $this->parseAddress((string) ($header->from ?? ''));
                if (!$account->isFromAddressAllowed($from['email'])) {
                    continue;
                }

                $message = (new MailboxMessage())->setMailbox($account)->setRemoteUid($uid);
                $message
                    ->setMessageId(isset($header->message_id) ? (string) $header->message_id : null)
                    ->setFromAddress($from['email'])
                    ->setFromName($from['name'])
                    ->setMessageGroup($account->resolveMessageGroup($from['email']))
                    ->setToAddresses(isset($header->to) ? (string) $header->to : null)
                    ->setSubject($this->decodeMime((string) ($header->subject ?? '(без теми)')))
                    ->setReceivedAt($this->resolveDate($header))
                    ->setIsSeen(!empty($header->seen))
                    ->setFolder('INBOX');

                $structure = @imap_fetchstructure($mailbox, (int) $uid, \FT_UID);
                [$text, $html] = $this->extractBodies($mailbox, $uid, $structure);
                $message->setBodyText($text);
                $message->setBodyHtml($html);
                $previewSource = $text !== null && $text !== '' ? $text : strip_tags((string) $html);
                $message->setBodyPreview($previewSource !== '' ? $previewSource : null);

                $attachments = $this->downloadAttachments($mailbox, $uid, $structure, $message);
                $message->setAttachments($attachments);
                $message->setHasAttachments($attachments !== [] || $this->detectAttachments($mailbox, $uid, $structure));

                $this->entityManager->persist($message);
                ++$imported;
            }

            if ($maxSeenUid > 0) {
                $account->setLastSyncedRemoteUid((string) $maxSeenUid);
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
            $this->closeMailbox($mailbox);
        }
    }

    /**
     * Download attachments for an already synced message that only has the flag set.
     *
     * @return list<array{name: string, stored: string, size: int, mime: string}>
     */
    public function ensureAttachments(MailboxMessage $message): array
    {
        if ($message->getAttachments() !== []) {
            return $message->getAttachments();
        }

        $account = $message->getMailbox();
        if ($account === null || !\function_exists('imap_open')) {
            return [];
        }

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
                return [];
            }

            $uid = $message->getRemoteUid();
            $structure = @imap_fetchstructure($mailbox, (int) $uid, \FT_UID);
            $attachments = $this->downloadAttachments($mailbox, $uid, $structure, $message);
            if ($attachments !== []) {
                $message->setAttachments($attachments);
                $this->entityManager->flush();
            }

            return $attachments;
        } catch (\Throwable $e) {
            $this->logger->warning('Mailbox attachment fetch failed.', [
                'message_id' => $message->getId(),
                'error' => $e->getMessage(),
            ]);

            return [];
        } finally {
            $this->closeMailbox($mailbox);
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

    /**
     * @param resource|\IMAP\Connection|false|null $mailbox
     */
    private function closeMailbox(mixed $mailbox): void
    {
        // Flush IMAP error queue so PHP shutdown does not turn leftover notices into fatals.
        imap_errors();
        imap_alerts();

        if (\is_resource($mailbox) || $mailbox instanceof \IMAP\Connection) {
            @imap_close($mailbox);
        }

        imap_errors();
        imap_alerts();
    }

    private function resolveLastSyncedUid(MailboxAccount $account): int
    {
        $stored = $account->getLastSyncedRemoteUid();
        if ($stored !== null && ctype_digit($stored)) {
            return (int) $stored;
        }

        return $this->messageRepository->findMaxRemoteUid($account) ?? 0;
    }

    /**
     * @param resource|\IMAP\Connection $mailbox
     *
     * @return list<int|string>
     */
    private function searchUidsAfter($mailbox, MailboxAccount $account, int $afterUid): array
    {
        if ($afterUid > 0) {
            // Prefer IMAP UID range when the server supports it.
            imap_errors();
            imap_alerts();
            $criteria = 'UID '.($afterUid + 1).':*';
            $found = @imap_search($mailbox, $criteria, \SE_UID);
            $imapErrors = imap_errors() ?: [];
            $uidSearchUnsupported = false;
            foreach ($imapErrors as $imapError) {
                if (stripos((string) $imapError, 'Unknown search criterion: UID') !== false
                    || stripos((string) $imapError, 'invalid search') !== false
                ) {
                    $uidSearchUnsupported = true;
                    break;
                }
            }

            if (\is_array($found) && $found !== [] && !$uidSearchUnsupported) {
                $uids = array_values($found);
                sort($uids, \SORT_NUMERIC);

                return \array_slice($uids, 0, self::FETCH_LIMIT);
            }

            // Fallback for hosts (e.g. mail.adm.tools) that reject UID search criteria:
            // fetch candidate UIDs and filter in PHP.
            $uids = array_values(array_filter(
                $this->searchAllCandidateUids($mailbox, $account),
                static fn (int|string $uid): bool => (int) $uid > $afterUid,
            ));
            sort($uids, \SORT_NUMERIC);

            return \array_slice($uids, 0, self::FETCH_LIMIT);
        }

        // First sync: take the newest messages only.
        $uids = $this->searchAllCandidateUids($mailbox, $account);
        if ($uids === []) {
            return [];
        }
        rsort($uids, \SORT_NUMERIC);

        return \array_slice($uids, 0, self::FETCH_LIMIT);
    }

    /**
     * @param resource|\IMAP\Connection $mailbox
     *
     * @return list<int|string>
     */
    private function searchAllCandidateUids($mailbox, MailboxAccount $account): array
    {
        $allowed = $account->getAllowedFromEmailList();
        // Empty filter or *|Group => fetch all senders.
        if ($allowed === [] || $account->hasWildcardFromFilter()) {
            imap_errors();
            $uids = @imap_search($mailbox, 'ALL', \SE_UID) ?: [];
            imap_errors();

            return \is_array($uids) ? array_values($uids) : [];
        }

        $uids = [];
        foreach ($allowed as $email) {
            $criteria = 'FROM "'.str_replace(['\\', '"'], ['\\\\', '\\"'], $email).'"';
            imap_errors();
            $found = @imap_search($mailbox, $criteria, \SE_UID) ?: [];
            imap_errors();
            if (\is_array($found)) {
                foreach ($found as $uid) {
                    $uids[(string) $uid] = $uid;
                }
            }
        }

        return array_values($uids);
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

    private function detectAttachments($mailbox, string $uid, mixed $structure = null): bool
    {
        $structure ??= @imap_fetchstructure($mailbox, (int) $uid, \FT_UID);
        if ($structure === false || $structure === null) {
            return false;
        }

        return $this->collectAttachmentParts($structure, '') !== [];
    }

    /**
     * @param resource|\IMAP\Connection $mailbox
     *
     * @return list<array{name: string, stored: string, size: int, mime: string}>
     */
    private function downloadAttachments($mailbox, string $uid, mixed $structure, MailboxMessage $message): array
    {
        if ($structure === false || $structure === null) {
            return [];
        }

        $saved = [];
        foreach ($this->collectAttachmentParts($structure, '') as $item) {
            $raw = imap_fetchbody($mailbox, (int) $uid, $item['part'], \FT_UID | \FT_PEEK) ?: '';
            if ($raw === '') {
                continue;
            }

            $binary = match ($item['encoding']) {
                3 => base64_decode($raw, true) ?: $raw,
                4 => quoted_printable_decode($raw),
                default => $raw,
            };
            if ($binary === '') {
                continue;
            }

            $meta = $this->attachmentStorage->save($message, $item['name'], $binary, $item['mime']);
            if ($meta !== null) {
                $saved[] = $meta;
            }
        }

        return $saved;
    }

    /**
     * @return list<array{part: string, name: string, mime: string, encoding: int}>
     */
    private function collectAttachmentParts(object $part, string $partNo): array
    {
        $found = [];
        if (isset($part->parts) && \is_array($part->parts) && $part->parts !== []) {
            foreach ($part->parts as $index => $child) {
                if (!\is_object($child)) {
                    continue;
                }
                $childNo = $partNo === '' ? (string) ($index + 1) : $partNo.'.'.($index + 1);
                $found = array_merge($found, $this->collectAttachmentParts($child, $childNo));
            }

            return $found;
        }

        $filename = $this->partFilename($part);
        $disposition = strtolower((string) ($part->disposition ?? ''));
        $type = (int) ($part->type ?? 0); // 0 = text
        $isAttachment = $disposition === 'attachment'
            || ($filename !== null && !($type === 0 && $disposition === 'inline'));

        if (!$isAttachment || $filename === null) {
            return [];
        }

        $subtype = strtolower((string) ($part->subtype ?? 'octet-stream'));
        $mimePrimary = match ($type) {
            0 => 'text',
            1 => 'multipart',
            2 => 'message',
            3 => 'application',
            4 => 'audio',
            5 => 'image',
            6 => 'video',
            default => 'application',
        };

        $found[] = [
            'part' => $partNo !== '' ? $partNo : '1',
            'name' => $this->decodeMime($filename),
            'mime' => $mimePrimary.'/'.$subtype,
            'encoding' => (int) ($part->encoding ?? 0),
        ];

        return $found;
    }

    private function partFilename(object $part): ?string
    {
        foreach ([$part->dparameters ?? null, $part->parameters ?? null] as $params) {
            if (!\is_array($params)) {
                continue;
            }
            foreach ($params as $param) {
                $attr = strtoupper((string) ($param->attribute ?? ''));
                if (\in_array($attr, ['FILENAME', 'NAME'], true)) {
                    $value = trim((string) ($param->value ?? ''));
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
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

        return $this->extractBodiesFromPart($mailbox, $uid, $structure, '');
    }

    /**
     * Recursively walk multipart trees (mixed/alternative/related).
     *
     * @param resource|\IMAP\Connection $mailbox
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function extractBodiesFromPart($mailbox, string $uid, object $part, string $partNo): array
    {
        $text = null;
        $html = null;
        $subtype = strtoupper((string) ($part->subtype ?? 'PLAIN'));
        $type = (int) ($part->type ?? 0); // 0 = text

        if (isset($part->parts) && \is_array($part->parts) && $part->parts !== []) {
            foreach ($part->parts as $index => $child) {
                if (!\is_object($child)) {
                    continue;
                }
                $childNo = $partNo === '' ? (string) ($index + 1) : $partNo.'.'.($index + 1);
                [$childText, $childHtml] = $this->extractBodiesFromPart($mailbox, $uid, $child, $childNo);
                if ($text === null || $text === '') {
                    $text = $childText;
                }
                if ($html === null || $html === '') {
                    $html = $childHtml;
                }
                if (($text !== null && $text !== '') && ($html !== null && $html !== '')) {
                    break;
                }
            }

            return [$text, $html];
        }

        // Leaf part.
        if ($type !== 0) {
            return [null, null];
        }

        $raw = $partNo === ''
            ? (imap_body($mailbox, (int) $uid, \FT_UID | \FT_PEEK) ?: '')
            : (imap_fetchbody($mailbox, (int) $uid, $partNo, \FT_UID | \FT_PEEK) ?: '');
        $decoded = $this->decodePart($raw, (int) ($part->encoding ?? 0), $this->partCharset($part));
        if ($decoded === '') {
            return [null, null];
        }

        if ($subtype === 'HTML') {
            return [null, $decoded];
        }

        return [$decoded, null];
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
