<?php

namespace App\PartnerProgram\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Registers a partner link view on the affiliate API (POST /api/views).
 */
final class AffiliateViewClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PartnerProgramLogger $logger,
        #[Autowire('%env(PARTNER_AFFILIATE_API_BASE)%')]
        private readonly string $apiBase,
    ) {
    }

    /**
     * @param array<string, string> $attribution Extra FB / Google Ads click fields
     *
     * @return array{ok: bool, advertisingLinkId: ?int}
     */
    public function registerView(
        string $referralKey,
        string $landingUrl,
        ?string $visitorId = null,
        ?string $ipAddress = null,
        array $attribution = [],
    ): array {
        $base = rtrim(trim($this->apiBase), '/');
        if ($base === '' || $referralKey === '') {
            return ['ok' => false, 'advertisingLinkId' => null];
        }

        $payload = [
            'key' => $referralKey,
            'url' => $landingUrl,
        ];
        $visitorId = $visitorId !== null ? trim($visitorId) : '';
        if ($visitorId !== '') {
            $payload['visitor_id'] = mb_substr($visitorId, 0, 64);
        }
        $ipAddress = $ipAddress !== null ? trim($ipAddress) : '';
        if ($ipAddress !== '') {
            $payload['ip_address'] = mb_substr($ipAddress, 0, 45);
        }
        foreach ($attribution as $key => $value) {
            if (!\is_string($key) || $key === '' || !\is_string($value) || $value === '') {
                continue;
            }
            $payload[$key] = $value;
        }

        try {
            $response = $this->httpClient->request('POST', $base.'/api/views', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 2.5,
                'max_duration' => 3.0,
                // Local/staging affiliate cert may be self-signed.
                'verify_peer' => false,
                'verify_host' => false,
            ]);

            $status = $response->getStatusCode();
            $data = $response->toArray(false);
            if ($status < 200 || $status >= 300 || !\is_array($data) || empty($data['ok'])) {
                return ['ok' => false, 'advertisingLinkId' => null];
            }

            $linkId = $data['view']['advertisingLinkId'] ?? null;

            return [
                'ok' => true,
                'advertisingLinkId' => is_numeric($linkId) ? (int) $linkId : null,
            ];
        } catch (TransportExceptionInterface|\Throwable $e) {
            $this->logger->warning('Partner affiliate view registration failed', [
                'key' => $referralKey,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'advertisingLinkId' => null];
        }
    }
}
