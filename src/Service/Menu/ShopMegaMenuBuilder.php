<?php

namespace App\Service\Menu;

use App\Entity\Category;
use App\Entity\ShopMegaMenuItem;
use App\Entity\ShopMegaMenuSetting;
use App\Repository\CategoryRepository;
use App\Repository\LocaleRepository;
use App\Repository\ShopMegaMenuSettingRepository;
use App\Repository\SiteRepository;

final class ShopMegaMenuBuilder
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly ShopMegaMenuSettingRepository $megaMenuSettingRepository,
        private readonly SiteRepository $siteRepository,
        private readonly LocaleRepository $localeRepository,
    ) {
    }

    /**
     * @return list<array{
     *   id: int,
     *   name: string,
     *   slug: string,
     *   children: list<array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     children: list<array{id: int, name: string, slug: string}>
     *   }>
     * }>
     */
    public function buildForHost(string $host, string $localeCode): array
    {
        $site = $this->siteRepository->findOneBy(['domain' => $host]);
        $locale = $this->localeRepository->findOneBy(['code' => $localeCode]);
        $setting = $site !== null && $locale !== null
            ? $this->megaMenuSettingRepository->findOneBySiteAndLocale($site, $locale)
            : null;

        return $this->build($setting);
    }

    /**
     * @return list<array{
     *   id: int,
     *   name: string,
     *   slug: string,
     *   children: list
     * }>
     */
    public function build(?ShopMegaMenuSetting $setting): array
    {
        if ($setting === null || !$setting->isManual()) {
            return $this->categoryRepository->buildStorefrontMenuTree();
        }

        $roots = [];
        foreach ($setting->getItems() as $item) {
            if (!$item instanceof ShopMegaMenuItem || !$item->isEnabled()) {
                continue;
            }
            $category = $item->getCategory();
            if (!$category instanceof Category || !$category->isEnabled() || $category->isDefault()) {
                continue;
            }
            $roots[] = $category;
        }

        if ($roots === []) {
            return $this->categoryRepository->buildStorefrontMenuTree();
        }

        // Admin chose these roots explicitly — always show them (children still auto-filtered).
        return $this->categoryRepository->buildStorefrontMenuTreeFromRoots($roots, true);
    }
}
