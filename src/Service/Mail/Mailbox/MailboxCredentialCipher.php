<?php

namespace App\Service\Mail\Mailbox;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encrypts mailbox passwords at rest using sodium secretbox + APP_SECRET.
 */
final class MailboxCredentialCipher
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
    ) {
    }

    public function encrypt(string $plainPassword): string
    {
        if ($plainPassword === '') {
            throw new \InvalidArgumentException('Password cannot be empty.');
        }

        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plainPassword, $nonce, $this->key());

        return base64_encode($nonce.$cipher);
    }

    public function decrypt(string $encrypted): string
    {
        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) < \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Invalid encrypted password payload.');
        }

        $nonce = substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key());
        if ($plain === false) {
            throw new \RuntimeException('Cannot decrypt mailbox password.');
        }

        return $plain;
    }

    private function key(): string
    {
        return hash('sha256', $this->appSecret, true);
    }
}
