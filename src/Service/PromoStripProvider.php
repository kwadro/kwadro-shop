<?php

namespace App\Service;

use App\Entity\HomepagePromoStripItem;
use App\Entity\HomepageSetting;
use App\Repository\HomepageSettingRepository;
use App\Repository\LocaleRepository;
use App\Repository\SiteRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class PromoStripProvider
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SiteRepository $siteRepository,
        private readonly LocaleRepository $localeRepository,
        private readonly HomepageSettingRepository $homepageSettingRepository,
    ) {
    }

    /**
     * @return array|null
     */
    public function getForCurrentRequest(): ?array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $site = $this->siteRepository->findOneBy(['domain' => $request->getHost()]);
        $locale = $this->localeRepository->findOneBy(['code' => $request->getLocale()]);
        if ($site === null || $locale === null) {
            return null;
        }

        $homeSetting = $this->homepageSettingRepository->findOneBySiteAndLocale($site, $locale);

        return $this->resolve($homeSetting);
    }

    /**
     * @return array|null
     */
    public function resolve(?HomepageSetting $homeSetting): ?array
    {
        if ($homeSetting === null || !$homeSetting->isPromoStripEnabled()) {
            return null;
        }

        $items = [];
        foreach ($homeSetting->getPromoStripItems() as $item) {
            if (!$item instanceof HomepagePromoStripItem || !$item->isEnabled()) {
                continue;
            }
            $content = trim($item->getContent());
            if ($content === '') {
                continue;
            }
            $items[] = $content;
        }

        if ($items === []) {
            return null;
        }

        $settingId = $homeSetting->getId() ?? 0;

        return [
            'enabled' => true,
            'dismissible' => $homeSetting->isPromoStripDismissible(),
            'intervalMs' => $homeSetting->getPromoStripIntervalMs(),
            'dismissKey' => 'kvadro-promo-strip-'.$settingId,
            'items' => $items,
        ];
    }
}
