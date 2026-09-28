<?php

namespace App\Service\Mail\Mailbox;

use App\Entity\MailboxAccount;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends email via the mailbox account's own SMTP credentials.
 */
final class MailboxSmtpSendService
{
    public function __construct(
        private readonly MailboxCredentialCipher $cipher,
    ) {
    }

    public function send(
        MailboxAccount $account,
        string $to,
        string $subject,
        string $body,
        bool $html = false,
    ): void {
        $password = $this->cipher->decrypt($account->getPasswordEncrypted());
        $encryption = match ($account->getSmtpEncryption()) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            default => 'smtp',
        };

        $dsn = sprintf(
            '%s://%s:%s@%s:%d',
            $encryption,
            rawurlencode($account->getUsername()),
            rawurlencode($password),
            $account->getSmtpHost(),
            $account->getSmtpPort(),
        );

        if ($account->getSmtpEncryption() === 'tls') {
            $dsn .= '?encryption=tls';
        }

        $mailer = new Mailer(Transport::fromDsn($dsn));
        $message = (new Email())
            ->from(new Address($account->getEmail(), $account->getName()))
            ->to($to)
            ->subject($subject);

        if ($html) {
            $message->html($body);
        } else {
            $message->text($body);
        }

        $mailer->send($message);
    }
}
