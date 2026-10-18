<?php

namespace App\Controller\Shop;

use App\Entity\Category;
use App\Entity\HomepageHeroSlide;
use App\Entity\HomepageSetting;
use App\Repository\BlogArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\HomepageSettingRepository;
use App\Repository\LocaleRepository;
use App\Repository\SiteRepository;
use App\Routing\ShopRoutes;
use App\Service\Menu\ShopMegaMenuBuilder;
use App\Service\ProductCatalog;
use Doctrine\Common\Collections\Collection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly ProductCatalog $productCatalog,
        private readonly CategoryRepository $categoryRepository,
        private readonly BlogArticleRepository $blogArticleRepository,
        private readonly SiteRepository $siteRepository,
        private readonly LocaleRepository $localeRepository,
        private readonly HomepageSettingRepository $homepageSettingRepository,
        private readonly ShopMegaMenuBuilder $megaMenuBuilder,
    ) {
    }

    #[Route(
        '/{_locale}',
        name: 'shop_home',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        priority: 20,
    )]
    #[Route(
        '/{_locale}/',
        name: 'shop_home_trailing_slash',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        priority: 20,
    )]
    public function index(string $_locale, Request $request): Response
    {
        return $this->renderHome($request, $_locale);
    }

    #[Route('/{_locale}/admser', name: 'shop_admser', requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS])]
    public function admser(string $_locale, Request $request): Response
    {
        $response = $this->renderHome($request, $_locale, showTopbar: true, blockSearchEngines: true);
        $response->headers->set('X-Robots-Tag', self::SEARCH_ENGINE_BLOCK_DIRECTIVE);

        return $response;
    }

    private function renderHome(
        Request $request,
        string $localeCode,
        bool $showTopbar = false,
        bool $blockSearchEngines = false,
    ): Response {
        $site = $this->siteRepository->findOneBy(['domain' => $request->getHost()]);
        $locale = $this->localeRepository->findOneBy(['code' => $localeCode]);
        $homeSetting = $site !== null && $locale !== null
            ? $this->homepageSettingRepository->findOneBySiteAndLocale($site, $locale)
            : null;

        $subtreeCounts = $this->categoryRepository->countEnabledProductsInSubtreeGroupedByCategoryId();
        $menu = $this->megaMenuBuilder->buildForHost($request->getHost(), $localeCode);
        $autoCategories = $this->mapMenuCategories($menu, $subtreeCounts);
        $categories = $this->resolveHomepageCategories($homeSetting, $autoCategories, $subtreeCounts);
        $categoriesPerRow = $homeSetting?->getCategoriesPerRow() ?? HomepageSetting::DEFAULT_CATEGORIES_PER_ROW;
        $primaryCategory = $categories[0] ?? $autoCategories[0] ?? null;

        $productsSliderMin = $homeSetting?->getProductsLimit() ?? HomepageSetting::DEFAULT_PRODUCTS_LIMIT;
        $products = [];
        if ($homeSetting === null || $homeSetting->isProductsEnabled()) {
            $products = $this->productCatalog->getHomepageProducts(
                $productsSliderMin,
                $this->manualSelectionIds($homeSetting?->getProductsSource(), $homeSetting?->getProducts()),
            );
        }

        $articles = [];
        if ($site !== null && $locale !== null && ($homeSetting === null || $homeSetting->isArticlesEnabled())) {
            $articles = $this->blogArticleRepository->findLatestPublished(
                $site,
                $locale,
                $homeSetting?->getArticlesLimit() ?? HomepageSetting::DEFAULT_ARTICLES_LIMIT,
                $this->manualSelectionIds($homeSetting?->getArticlesSource(), $homeSetting?->getArticles()),
            );
        }

        $heroSlides = $this->resolveHeroSlides($homeSetting);

        return $this->render('shop/home/index.html.twig', [
            'heroSlides' => $heroSlides,
            'categories' => $categories,
            'categoriesPerRow' => $categoriesPerRow,
            'primaryCategory' => $primaryCategory,
            'products' => $products,
            'productsSliderMin' => $productsSliderMin,
            'articles' => $articles,
            'homeBlocks' => [
                'products_title' => $homeSetting?->getProductsTitle(),
                'products_lead' => $homeSetting?->getProductsLead(),
                'articles_title' => $homeSetting?->getArticlesTitle(),
                'articles_lead' => $homeSetting?->getArticlesLead(),
            ],
            'show_topbar' => $showTopbar,
            'block_search_engines' => $blockSearchEngines,
        ]);
    }

    /**
     * @return list<array{
     *     brand: ?string,
     *     title: ?string,
     *     lead: ?string,
     *     image: ?string,
     *     imagePosition: int,
     *     primaryCtaLabel: ?string,
     *     primaryCtaUrl: ?string,
     *     secondaryCtaLabel: ?string,
     *     secondaryCtaUrl: ?string,
     *     isDefault: bool
     * }>
     */
    private function resolveHeroSlides(?HomepageSetting $homeSetting): array
    {
        if ($homeSetting !== null && !$homeSetting->isHeroEnabled()) {
            return [];
        }

        $slides = [];
        if ($homeSetting !== null) {
            foreach ($homeSetting->getHeroSlides() as $slide) {
                if (!$slide instanceof HomepageHeroSlide || !$slide->isEnabled()) {
                    continue;
                }
                $slides[] = [
                    'brand' => $slide->getBrand(),
                    'title' => $slide->getTitle(),
                    'lead' => $slide->getLead(),
                    'image' => $slide->getImage(),
                    'imagePosition' => $slide->getImagePosition(),
                    'primaryCtaLabel' => $slide->getPrimaryCtaLabel(),
                    'primaryCtaUrl' => $slide->getPrimaryCtaUrl(),
                    'secondaryCtaLabel' => $slide->getSecondaryCtaLabel(),
                    'secondaryCtaUrl' => $slide->getSecondaryCtaUrl(),
                    'isDefault' => false,
                ];
            }
        }

        if ($slides !== []) {
            return $slides;
        }

        // No configured slides: keep the previous single default hero.
        return [[
            'brand' => null,
            'title' => null,
            'lead' => null,
            'image' => null,
            'imagePosition' => HomepageHeroSlide::DEFAULT_IMAGE_POSITION,
            'primaryCtaLabel' => null,
            'primaryCtaUrl' => null,
            'secondaryCtaLabel' => null,
            'secondaryCtaUrl' => null,
            'isDefault' => true,
        ]];
    }

    /**
     * @param list<array{id: int, name: string, slug: string, og_image?: ?string, children?: list}> $menu
     * @param array<int, int> $subtreeCounts
     *
     * @return list<array{id: int, name: string, slug: string, ogImage: ?string, productCount: int, childrenCount: int}>
     */
    private function mapMenuCategories(array $menu, array $subtreeCounts): array
    {
        $categories = [];
        foreach ($menu as $item) {
            $id = (int) ($item['id'] ?? 0);
            $categories[] = [
                'id' => $id,
                'name' => (string) $item['name'],
                'slug' => (string) $item['slug'],
                'ogImage' => $item['og_image'] ?? null,
                'productCount' => $subtreeCounts[$id] ?? 0,
                'childrenCount' => \count($item['children'] ?? []),
            ];
        }

        return $categories;
    }

    /**
     * @param list<array{id: int, name: string, slug: string, ogImage: ?string, productCount: int, childrenCount: int}> $autoCategories
     * @param array<int, int> $subtreeCounts
     *
     * @return list<array{id: int, name: string, slug: string, ogImage: ?string, productCount: int, childrenCount: int}>
     */
    private function resolveHomepageCategories(
        ?HomepageSetting $homeSetting,
        array $autoCategories,
        array $subtreeCounts,
    ): array {
        if ($homeSetting !== null && !$homeSetting->isCategoriesEnabled()) {
            return [];
        }

        if ($homeSetting?->getCategoriesSource() !== HomepageSetting::SOURCE_MANUAL) {
            return $autoCategories;
        }

        $ordered = [];
        foreach ($homeSetting->getCategoryItems() as $item) {
            if (!$item->isEnabled()) {
                continue;
            }
            $category = $item->getCategory();
            if (!$category instanceof Category || !$category->isEnabled() || $category->isDefault()) {
                continue;
            }
            $id = $category->getId();
            if ($id === null) {
                continue;
            }
            $childrenCount = 0;
            foreach ($category->getChildren() as $child) {
                if ($child->isEnabled()) {
                    ++$childrenCount;
                }
            }
            $ordered[] = [
                'id' => $id,
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'ogImage' => $category->getOgImage(),
                'productCount' => $subtreeCounts[$id] ?? 0,
                'childrenCount' => $childrenCount,
            ];
        }

        // Empty manual list falls back to auto (same as products/articles).
        return $ordered !== [] ? $ordered : $autoCategories;
    }

    /**
     * Manual source with an empty selection falls back to the latest items.
     *
     * @param Collection<int, object>|null $selected
     *
     * @return list<int>|null
     */
    private function manualSelectionIds(?string $source, ?Collection $selected): ?array
    {
        if ($source !== HomepageSetting::SOURCE_MANUAL || $selected === null || $selected->isEmpty()) {
            return null;
        }

        return array_values(array_filter(
            $selected->map(static fn (object $item): ?int => $item->getId())->toArray(),
            static fn (?int $id): bool => $id !== null,
        ));
    }

    private const SEARCH_ENGINE_BLOCK_DIRECTIVE = 'noindex, nofollow, noarchive, nosnippet';
}
