<?php

namespace App\Controller\Shop;

use App\Entity\HomepageSetting;
use App\Repository\BlogArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\HomepageSettingRepository;
use App\Repository\LocaleRepository;
use App\Repository\SiteRepository;
use App\Routing\ShopRoutes;
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
        $menu = $this->categoryRepository->buildStorefrontMenuTree();
        $subtreeCounts = $this->categoryRepository->countEnabledProductsInSubtreeGroupedByCategoryId();
        $categories = [];
        foreach ($menu as $item) {
            $id = (int) ($item['id'] ?? 0);
            $categories[] = [
                'id' => $id,
                'name' => $item['name'],
                'slug' => $item['slug'],
                'ogImage' => $item['og_image'] ?? null,
                'productCount' => $subtreeCounts[$id] ?? 0,
                'childrenCount' => \count($item['children'] ?? []),
            ];
        }

        $primaryCategory = $categories[0] ?? null;

        $site = $this->siteRepository->findOneBy(['domain' => $request->getHost()]);
        $locale = $this->localeRepository->findOneBy(['code' => $localeCode]);
        $homeSetting = $site !== null && $locale !== null
            ? $this->homepageSettingRepository->findOneBySiteAndLocale($site, $locale)
            : null;

        $products = [];
        if ($homeSetting === null || $homeSetting->isProductsEnabled()) {
            $products = $this->productCatalog->getHomepageProducts(
                $homeSetting?->getProductsLimit() ?? HomepageSetting::DEFAULT_PRODUCTS_LIMIT,
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

        return $this->render('shop/home/index.html.twig', [
            'categories' => $categories,
            'primaryCategory' => $primaryCategory,
            'products' => $products,
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
