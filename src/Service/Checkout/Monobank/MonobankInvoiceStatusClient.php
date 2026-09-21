<?php

namespace App\Service\Checkout\Monobank;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MonobankInvoiceStatusClient
{
    private const STATUS_URL = 'https://api.monobank.ua/api/merchant/invoice/status';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $monobankToken,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->monobankToken !== null && $this->monobankToken !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchStatus(string $invoiceId): ?array
    {
        if (!$this->isConfigured() || $invoiceId === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', self::STATUS_URL, [
                'headers' => ['X-Token' => $this->monobankToken],
                'query' => ['invoiceId' => $invoiceId],
                'timeout' => 15,
            ])->toArray(false);
        } catch (\Throwable) {
            return null;
        }

        return \is_array($response) ? $response : null;
    }
}
