<?php

namespace App\Service\Mail\Mailbox;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encrypts mailbox passwords at rest (OpenSSL AES-256-GCM + APP_SECRET).
 * Also decrypts legacy sodium payloads when ext-sodium is available.
 */
final class MailboxCredentialCipher
{
    private const PREFIX_OPENSSL = 'o1:';
    private const IV_LENGTH = 12;

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

        if (!\extension_loaded('openssl')) {
            throw new \RuntimeException('PHP extension "openssl" is required to store mailbox passwords.');
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $cipher = openssl_encrypt(
            $plainPassword,
            'aes-256-gcm',
            $this->key(),
            \OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16,
        );

        if ($cipher === false) {
            throw new \RuntimeException('Cannot encrypt mailbox password.');
        }

        return self::PREFIX_OPENSSL.base64_encode($iv.$tag.$cipher);
    }

    public function decrypt(string $encrypted): string
    {
        if (str_starts_with($encrypted, self::PREFIX_OPENSSL)) {
            return $this->decryptOpenssl(substr($encrypted, \strlen(self::PREFIX_OPENSSL)));
        }

        return $this->decryptLegacySodium($encrypted);
    }

    private function decryptOpenssl(string $payload): string
    {
        if (!\extension_loaded('openssl')) {
            throw new \RuntimeException('PHP extension "openssl" is required to read mailbox passwords.');
        }

        $raw = base64_decode($payload, true);
        if ($raw === false || \strlen($raw) < self::IV_LENGTH + 16) {
            throw new \RuntimeException('Invalid encrypted password payload.');
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, 16);
        $cipher = substr($raw, self::IV_LENGTH + 16);
        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            $this->key(),
            \OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plain === false) {
            throw new \RuntimeException('Cannot decrypt mailbox password.');
        }

        return $plain;
    }

    private function decryptLegacySodium(string $encrypted): string
    {
        if (!\extension_loaded('sodium') || !\defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES')) {
            throw new \RuntimeException(
                'Legacy mailbox password cannot be decrypted: enable PHP sodium for web (php-fpm/apache), or re-save the password in admin.'
            );
        }

        $nonceBytes = \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        $raw = base64_decode($encrypted, true);
        if ($raw === false || \strlen($raw) < $nonceBytes) {
            throw new \RuntimeException('Invalid encrypted password payload.');
        }

        $nonce = substr($raw, 0, $nonceBytes);
        $cipher = substr($raw, $nonceBytes);
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
