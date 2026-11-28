<?php

namespace App\PartnerProgram\Service;

use App\PartnerProgram\Entity\PartnerReferralHit;
use App\PartnerProgram\Repository\PartnerQueryParameterRepository;
use App\Service\Cart\VisitorIdResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Detects partner referral from admin-configured query params and/or path suffix
 * /{pageCode}/{linkKey} where pageCode is a 2-char name from the same admin list.
 * Stores local hit stats (+ FB/Google Ads click ids), cookies, notifies affiliate API,
 * 302-redirects to a cleaned URL.
 */
final class PartnerReferralTracker
{
    public const COOKIE_REF = 'bb_ref';
    public const COOKIE_REF_ID = 'bb_ref_id';
    private const COOKIE_DAYS = 30;

    public function __construct(
        private readonly PartnerQueryParameterRepository $queryParameterRepository,
        private readonly ReferralKeyParser $referralKeyParser,
        private readonly AffiliateViewClient $affiliateViewClient,
        private readonly AdClickAttribution $adClickAttribution,
        private readonly VisitorIdResolver $visitorIdResolver,
        private readonly EntityManagerInterface $entityManager,
        private readonly PartnerProgramLogger $logger,
    ) {
    }

    public function trackAndRedirect(Request $request): ?RedirectResponse
    {
        $this->logger->info('trackAndRedirect started', [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
        ]);

        if (!$request->isMethod('GET')) {
            return null;
        }

        $allowedParamNames = $this->queryParameterRepository->findActiveNames();
        $allowedPageCodes = $this->referralKeyParser->filterPathPageCodes($allowedParamNames);
        $this->logger->info('trackAndRedirect allowedParamNames', [
            'names' => $allowedParamNames,
            'pathPageCodes' => $allowedPageCodes,
        ]);

        $fromQuery = $this->extractFromQuery($request, $allowedParamNames);
        $pathInfo = $request->getPathInfo();
        $fromPath = $this->referralKeyParser->parsePath($pathInfo, $allowedPageCodes);

        $parsed = $fromQuery ?? $fromPath;
        if ($parsed === null) {
            $this->logger->info('trackAndRedirect no referral found');

            return null;
        }

        $needsQueryStrip = $fromQuery !== null;
        $needsPathStrip = $fromPath !== null;
        $this->logger->info('trackAndRedirect referral matched', [
            'referralKey' => $parsed['referralKey'],
            'needsQueryStrip' => $needsQueryStrip,
            'needsPathStrip' => $needsPathStrip,
            'source' => $fromQuery !== null ? 'query' : 'path',
            'param' => $fromQuery['param'] ?? $fromPath['pageCode'] ?? null,
        ]);

        $landingUrl = $request->getUri();
        $visitorId = $this->visitorIdResolver->resolve($request);
        $ipAddress = trim((string) ($request->getClientIp() ?? ''));
        $attribution = $this->adClickAttribution->extract($request);
        $apiResult = $this->affiliateViewClient->registerView(
            $parsed['referralKey'],
            $landingUrl,
            $visitorId,
            $ipAddress !== '' ? $ipAddress : null,
            $this->adClickAttribution->toApiPayload($attribution),
        );

        $param = $fromQuery['param'] ?? $fromPath['pageCode'] ?? null;
        $this->persistHit(
            $parsed['referralKey'],
            \is_string($param) ? $param : null,
            $landingUrl,
            $visitorId,
            $ipAddress !== '' ? $ipAddress : null,
            $attribution,
            $apiResult['advertisingLinkId'],
        );

        $cleanPath = $needsPathStrip
            ? $this->referralKeyParser->stripPathSuffix($pathInfo)
            : $pathInfo;

        $query = $request->query->all();
        if ($needsQueryStrip) {
            foreach ($allowedParamNames as $name) {
                unset($query[$name]);
                foreach (array_keys($query) as $existing) {
                    if (\is_string($existing) && strcasecmp($existing, $name) === 0) {
                        unset($query[$existing]);
                    }
                }
            }
        }

        $target = $cleanPath;
        if ($query !== []) {
            $qs = http_build_query($query);
            if ($qs !== '') {
                $target .= '?'.$qs;
            }
        }

        $response = new RedirectResponse($target !== '' ? $target : '/', 302);
        $this->attachCookies(
            $response,
            $request,
            $parsed['referralKey'],
            $apiResult['advertisingLinkId'],
            $attribution,
        );

        $this->logger->info('trackAndRedirect redirect', [
            'target' => $target,
            'visitorId' => $visitorId,
            'apiOk' => $apiResult['ok'],
            'advertisingLinkId' => $apiResult['advertisingLinkId'],
            'adSource' => $attribution['source'],
            'fbclid' => $attribution['fbclid'] !== null,
            'gclid' => $attribution['gclid'] !== null,
        ]);

        return $response;
    }

    /**
     * @param array{
     *     source: ?string,
     *     fbclid: ?string,
     *     fbc: ?string,
     *     fbp: ?string,
     *     gclid: ?string,
     *     gbraid: ?string,
     *     wbraid: ?string,
     *     utm_source: ?string,
     *     utm_medium: ?string,
     *     utm_campaign: ?string,
     *     new_fbc: ?string,
     *     new_fbp: ?string
     * } $attribution
     */
    private function persistHit(
        string $referralKey,
        ?string $param,
        string $landingUrl,
        ?string $visitorId,
        ?string $ipAddress,
        array $attribution,
        ?int $advertisingLinkId,
    ): void {
        $hit = (new PartnerReferralHit())
            ->setReferralKey($referralKey)
            ->setParam($param)
            ->setLandingUrl($landingUrl)
            ->setVisitorId($visitorId)
            ->setIpAddress($ipAddress)
            ->setAdSource($attribution['source'])
            ->setFbclid($attribution['fbclid'])
            ->setFbc($attribution['fbc'])
            ->setFbp($attribution['fbp'])
            ->setGclid($attribution['gclid'])
            ->setGbraid($attribution['gbraid'])
            ->setWbraid($attribution['wbraid'])
            ->setUtmSource($attribution['utm_source'])
            ->setUtmMedium($attribution['utm_medium'])
            ->setUtmCampaign($attribution['utm_campaign'])
            ->setAdvertisingLinkId($advertisingLinkId);

        try {
            $this->entityManager->persist($hit);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->warning('Partner referral hit persist failed', [
                'key' => $referralKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param list<string> $allowedParamNames
     *
     * @return array{pageCode: string, linkKey: string, referralKey: string, param: string}|null
     */
    private function extractFromQuery(Request $request, array $allowedParamNames): ?array
    {
        if ($allowedParamNames === []) {
            return null;
        }

        $query = $request->query->all();
        foreach ($allowedParamNames as $name) {
            $raw = $query[$name] ?? null;
            if (!\is_string($raw) || trim($raw) === '') {
                foreach ($query as $key => $value) {
                    if (\is_string($key) && strcasecmp($key, $name) === 0 && \is_string($value)) {
                        $raw = $value;
                        break;
                    }
                }
            }
            if (!\is_string($raw)) {
                continue;
            }
            $parsed = $this->referralKeyParser->parseReferralKey($raw);
            if ($parsed !== null) {
                $parsed['param'] = $name;

                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param array{
     *     source: ?string,
     *     fbclid: ?string,
     *     fbc: ?string,
     *     fbp: ?string,
     *     gclid: ?string,
     *     gbraid: ?string,
     *     wbraid: ?string,
     *     utm_source: ?string,
     *     utm_medium: ?string,
     *     utm_campaign: ?string,
     *     new_fbc: ?string,
     *     new_fbp: ?string
     * } $attribution
     */
    private function attachCookies(
        RedirectResponse $response,
        Request $request,
        string $referralKey,
        ?int $advertisingLinkId,
        array $attribution,
    ): void {
        $expire = time() + (self::COOKIE_DAYS * 24 * 60 * 60);
        $secure = $request->isSecure();

        $response->headers->setCookie(
            Cookie::create(self::COOKIE_REF)
                ->withValue($referralKey)
                ->withExpires($expire)
                ->withPath('/')
                ->withSecure($secure)
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX),
        );

        if ($advertisingLinkId !== null && $advertisingLinkId > 0) {
            $response->headers->setCookie(
                Cookie::create(self::COOKIE_REF_ID)
                    ->withValue((string) $advertisingLinkId)
                    ->withExpires($expire)
                    ->withPath('/')
                    ->withSecure($secure)
                    ->withHttpOnly(true)
                    ->withSameSite(Cookie::SAMESITE_LAX),
            );
        }

        // Match fbevents.js: persist _fbc/_fbp so later hits keep FB click context.
        if (\is_string($attribution['new_fbc']) && $attribution['new_fbc'] !== '') {
            $response->headers->setCookie(
                Cookie::create(AdClickAttribution::COOKIE_FBC)
                    ->withValue($attribution['new_fbc'])
                    ->withExpires($expire)
                    ->withPath('/')
                    ->withSecure($secure)
                    ->withHttpOnly(false)
                    ->withSameSite(Cookie::SAMESITE_LAX),
            );
        }
        if (\is_string($attribution['new_fbp']) && $attribution['new_fbp'] !== '') {
            $response->headers->setCookie(
                Cookie::create(AdClickAttribution::COOKIE_FBP)
                    ->withValue($attribution['new_fbp'])
                    ->withExpires($expire)
                    ->withPath('/')
                    ->withSecure($secure)
                    ->withHttpOnly(false)
                    ->withSameSite(Cookie::SAMESITE_LAX),
            );
        }
        if (\is_string($attribution['gclid']) && $attribution['gclid'] !== '') {
            $response->headers->setCookie(
                Cookie::create(AdClickAttribution::COOKIE_GCLID)
                    ->withValue('GCL.'.time().'.'.$attribution['gclid'])
                    ->withExpires($expire)
                    ->withPath('/')
                    ->withSecure($secure)
                    ->withHttpOnly(false)
                    ->withSameSite(Cookie::SAMESITE_LAX),
            );
        }
    }
}
