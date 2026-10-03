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

    /**
     * @param list<string>|string $to
     * @param list<string>        $cc
     * @param list<string>        $bcc
     */
    public function send(
        MailboxAccount $account,
        array|string $to,
        string $subject,
        string $body,
        bool $html = false,
        array $cc = [],
        array $bcc = [],
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

        $toList = $this->normalizeAddresses($to);
        if ($toList === []) {
            throw new \InvalidArgumentException('At least one recipient is required.');
        }

        $mailer = new Mailer(Transport::fromDsn($dsn));
        $message = (new Email())
            ->from(new Address($account->getEmail(), $account->getName()))
            ->subject($subject);

        foreach ($toList as $address) {
            $message->addTo($address);
        }
        foreach ($this->normalizeAddresses($cc) as $address) {
            $message->addCc($address);
        }
        foreach ($this->normalizeAddresses($bcc) as $address) {
            $message->addBcc($address);
        }

        if ($html) {
            $message->html($body);
            $message->text(trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        } else {
            $message->text($body);
        }

        $mailer->send($message);
    }

    /**
     * @param list<string>|string $addresses
     *
     * @return list<string>
     */
    private function normalizeAddresses(array|string $addresses): array
    {
        if (\is_string($addresses)) {
            $addresses = preg_split('/[\s,;]+/u', $addresses) ?: [];
        }

        $normalized = [];
        foreach ($addresses as $address) {
            $email = strtolower(trim((string) $address));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $normalized[$email] = true;
        }

        return array_keys($normalized);
    }
}
