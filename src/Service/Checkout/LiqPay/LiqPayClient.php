<?php

namespace App\Service\Checkout\LiqPay;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class LiqPayClient
{
    private const API_URL = 'https://www.liqpay.ua/api/request';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $liqpayPublicKey,
        private readonly ?string $liqpayPrivateKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->liqpayPublicKey !== null && $this->liqpayPublicKey !== ''
            && $this->liqpayPrivateKey !== null && $this->liqpayPrivateKey !== '';
    }

    public function sign(string $data): string
    {
        return base64_encode(sha1(($this->liqpayPrivateKey ?? '') . $data . ($this->liqpayPrivateKey ?? ''), true));
    }

    public function isValidSignature(string $data, string $signature): bool
    {
        if (!$this->isConfigured() || $data === '' || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($data), $signature);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeData(string $encodedData): ?array
    {
        if ($encodedData === '') {
            return null;
        }

        $decoded = json_decode(base64_decode($encodedData, true) ?: '', true);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchStatus(string $orderId): ?array
    {
        if (!$this->isConfigured() || $orderId === '') {
            return null;
        }

        $params = [
            'action' => 'status',
            'version' => 3,
            'public_key' => $this->liqpayPublicKey,
            'order_id' => $orderId,
        ];

        $data = base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE) ?: '{}');
        $signature = $this->sign($data);

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'body' => [
                    'data' => $data,
                    'signature' => $signature,
                ],
                'timeout' => 15,
            ])->toArray(false);
        } catch (\Throwable) {
            return null;
        }

        return \is_array($response) ? $response : null;
    }

    public static function isSuccessStatus(string $status): bool
    {
        return in_array($status, ['success', 'sandbox'], true);
    }

    public static function isFailureStatus(string $status): bool
    {
        return in_array($status, ['failure', 'error', 'reversed', 'expired'], true);
    }
}
