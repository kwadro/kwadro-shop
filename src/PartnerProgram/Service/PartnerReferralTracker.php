<?php

namespace App\PartnerProgram\Service;

use App\PartnerProgram\Repository\PartnerQueryParameterRepository;
use App\Service\Cart\VisitorIdResolver;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Detects partner referral from admin-configured query params and/or path suffix
 * /{pageCode}/{linkKey} where pageCode is a 2-char name from the same admin list.
 * Stores cookies, notifies affiliate API, 302-redirects to a cleaned URL.
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
        private readonly VisitorIdResolver $visitorIdResolver,
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
        $apiResult = $this->affiliateViewClient->registerView(
            $parsed['referralKey'],
            $landingUrl,
            $visitorId,
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
        $this->attachCookies($response, $request, $parsed['referralKey'], $apiResult['advertisingLinkId']);

        $this->logger->info('trackAndRedirect redirect', [
            'target' => $target,
            'visitorId' => $visitorId,
            'apiOk' => $apiResult['ok'],
            'advertisingLinkId' => $apiResult['advertisingLinkId'],
        ]);

        return $response;
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

    private function attachCookies(
        RedirectResponse $response,
        Request $request,
        string $referralKey,
        ?int $advertisingLinkId,
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
    }
}
