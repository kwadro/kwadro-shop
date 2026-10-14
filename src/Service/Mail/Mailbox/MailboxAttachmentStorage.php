<?php

namespace App\Service\Mail\Mailbox;

use App\Entity\MailboxMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stores mailbox attachments under var/mailbox/{accountId}/{remoteUid}/.
 */
final class MailboxAttachmentStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function directoryFor(MailboxMessage $message): string
    {
        $accountId = (int) ($message->getMailbox()?->getId() ?? 0);
        $uid = preg_replace('/[^0-9A-Za-z_-]/', '_', $message->getRemoteUid()) ?: '0';

        return $this->projectDir.'/var/mailbox/'.$accountId.'/'.$uid;
    }

    public function absolutePath(MailboxMessage $message, string $storedName): ?string
    {
        $storedName = basename($storedName);
        if ($storedName === '' || $storedName === '.' || $storedName === '..') {
            return null;
        }

        $path = $this->directoryFor($message).'/'.$storedName;
        $realBase = realpath($this->directoryFor($message));
        $realFile = realpath($path);
        if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase.DIRECTORY_SEPARATOR)) {
            return is_file($path) ? $path : null;
        }

        return $realFile;
    }

    public function save(MailboxMessage $message, string $originalName, string $binary, string $mime): ?array
    {
        $dir = $this->directoryFor($message);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $safeName = $this->sanitizeFilename($originalName);
        $target = $dir.'/'.$safeName;
        $suffix = 1;
        while (is_file($target)) {
            $target = $dir.'/'.$suffix.'_'.$safeName;
            ++$suffix;
        }

        if (file_put_contents($target, $binary) === false) {
            return null;
        }

        return [
            'name' => $originalName !== '' ? mb_substr($originalName, 0, 255) : basename($target),
            'stored' => basename($target),
            'size' => filesize($target) ?: \strlen($binary),
            'mime' => $mime !== '' ? mb_substr($mime, 0, 128) : 'application/octet-stream',
        ];
    }

    private function sanitizeFilename(string $name): string
    {
        $name = basename(str_replace(["\0", '\\'], '', $name));
        $name = preg_replace('/[^\p{L}\p{N}\.\-_ ]+/u', '_', $name) ?? 'attachment';
        $name = trim($name, ' ._');
        if ($name === '') {
            $name = 'attachment.bin';
        }

        return mb_substr($name, 0, 180);
    }
}
