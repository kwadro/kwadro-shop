<?php

namespace App\Service\Checkout\Monobank;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MonobankPublicKeyProvider
{
    private const PUBKEY_URL = 'https://api.monobank.ua/api/merchant/pubkey';
    private const CACHE_TTL_SECONDS = 86400;

    private ?string $cachedPem = null;
    private ?int $cachedAt = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $monobankToken,
    ) {
    }

    public function getPublicKeyPem(): string
    {
        if ($this->cachedPem !== null && $this->cachedAt !== null && (time() - $this->cachedAt) < self::CACHE_TTL_SECONDS) {
            return $this->cachedPem;
        }

        if ($this->monobankToken === null || $this->monobankToken === '') {
            throw new \RuntimeException('Monobank token is not configured.');
        }

        $response = $this->httpClient->request('GET', self::PUBKEY_URL, [
            'headers' => ['X-Token' => $this->monobankToken],
            'timeout' => 15,
        ])->toArray(false);

        $encodedKey = $response['key'] ?? null;
        if (!\is_string($encodedKey) || $encodedKey === '') {
            throw new \RuntimeException('Monobank public key response is invalid.');
        }

        $pem = base64_decode($encodedKey, true);
        if (!\is_string($pem) || $pem === '') {
            throw new \RuntimeException('Monobank public key decoding failed.');
        }

        $this->cachedPem = $pem;
        $this->cachedAt = time();

        return $pem;
    }
}
