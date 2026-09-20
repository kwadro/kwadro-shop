<?php

namespace App\Service\Checkout\Monobank;

class MonobankWebhookSignatureVerifier
{
    public function __construct(
        private readonly MonobankPublicKeyProvider $publicKeyProvider,
        private readonly ?string $monobankToken,
    ) {
    }

    public function verify(string $rawBody, ?string $xSignHeader): bool
    {
        if ($this->monobankToken === null || $this->monobankToken === '') {
            return false;
        }

        if ($xSignHeader === null || $xSignHeader === '') {
            return false;
        }

        $signature = base64_decode($xSignHeader, true);
        if ($signature === false) {
            return false;
        }

        $publicKey = openssl_pkey_get_public($this->publicKeyProvider->getPublicKeyPem());
        if ($publicKey === false) {
            return false;
        }

        return openssl_verify($rawBody, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }
}
