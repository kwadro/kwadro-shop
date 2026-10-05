<?php

namespace App\Controller\Shop;

use App\Entity\BlogArticle;
use App\Repository\BlogArticleRepository;
use App\Repository\BlogCategoryRepository;
use App\Repository\LocaleRepository;
use App\Repository\SiteRepository;
use App\Routing\ShopRoutes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlogController extends AbstractController
{
    private const MONTH_KEYS = [
        1 => 'january', 2 => 'february', 3 => 'march', 4 => 'april',
        5 => 'may', 6 => 'june', 7 => 'july', 8 => 'august',
        9 => 'september', 10 => 'october', 11 => 'november', 12 => 'december',
    ];

    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly LocaleRepository $localeRepository,
        private readonly BlogArticleRepository $articleRepository,
        private readonly BlogCategoryRepository $categoryRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        '/{_locale}/blog',
        name: 'shop_blog_index',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['GET'],
    )]
    public function index(string $_locale, Request $request): Response
    {
        [$site, $locale] = $this->resolveSiteLocale($request, $_locale);
        $articles = $this->articleRepository->findPublishedBySiteAndLocale($site, $locale);

        return $this->render('shop/blog/index.html.twig', $this->blogViewData($site, $locale, [
            'articles' => $articles,
            'groupedArticles' => $this->groupArticlesByMonth($articles),
        ]));
    }

    #[Route(
        '/{_locale}/blog/search',
        name: 'shop_blog_search',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['GET'],
    )]
    public function search(string $_locale, Request $request): Response
    {
        [$site, $locale] = $this->resolveSiteLocale($request, $_locale);
        $query = trim((string) $request->query->get('q', ''));
        $articles = $query !== ''
            ? $this->articleRepository->searchPublished($site, $locale, $query)
            : [];

        return $this->render('shop/blog/search.html.twig', $this->blogViewData($site, $locale, [
            'query' => $query,
            'articles' => $articles,
        ]));
    }

    #[Route(
        '/{_locale}/blog/archive',
        name: 'shop_blog_archive',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['GET'],
    )]
    public function archive(string $_locale, Request $request): Response
    {
        [$site, $locale] = $this->resolveSiteLocale($request, $_locale);

        return $this->render('shop/blog/archive.html.twig', $this->blogViewData($site, $locale, [
            'months' => $this->monthLabels(
                $this->articleRepository->findPublishedMonthBuckets($site, $locale)
            ),
        ]));
    }

    #[Route(
        '/{_locale}/blog/archive/{year}/{month}',
        name: 'shop_blog_archive_month',
        requirements: [
            '_locale' => ShopRoutes::LOCALE_REQUIREMENTS,
            'year' => '\d{4}',
            'month' => '0?[1-9]|1[0-2]',
        ],
        methods: ['GET'],
    )]
    public function archiveMonth(string $_locale, int $year, int $month, Request $request): Response
    {
        [$site, $locale] = $this->resolveSiteLocale($request, $_locale);
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw new NotFoundHttpException();
        }

        $articles = $this->articleRepository->findPublishedByYearMonth($site, $locale, $year, $month);
        $monthLabel = $this->monthName($month);

        return $this->render('shop/blog/archive_month.html.twig', $this->blogViewData($site, $locale, [
            'year' => $year,
            'month' => $month,
            'monthLabel' => $monthLabel,
            'articles' => $articles,
        ]));
    }

    #[Route(
        '/{_locale}/blog/category/{slug}',
        name: 'shop_blog_category',
        requirements: [
            '_locale' => ShopRoutes::LOCALE_REQUIREMENTS,
            'slug' => ShopRoutes::SLUG_REQUIREMENTS,
        ],
        methods: ['GET'],
    )]
    public function category(string $_locale, string $slug, Request $request): Response
    {
        [$site, $locale] = $this->resolveSiteLocale($request, $_locale);
        $category = $this->categoryRepository->findOneEnabledBySiteLocaleAndSlug($site, $locale, $slug);
        if ($category === null) {
            throw new NotFoundHttpException();
        }

        return $this->render('shop/blog/category.html.twig', $this->blogViewData($site, $locale, [
            'category' => $category,
            'articles' => $this->articleRepository->findPublishedByCategory($category),
        ]));
    }

    #[Route(
        '/{_locale}/blog/{slug}',
        name: 'shop_blog_article',
        requirements: [
            '_locale' => ShopRoutes::LOCALE_REQUIREMENTS,
            'slug' => ShopRoutes::SLUG_REQUIREMENTS,
        ],
        methods: ['GET'],
    )]
    public function show(string $_locale, string $slug, Request $request): Response
    {
        [$site, $locale] = $this->resolveSiteLocale($request, $_locale);
        $article = $this->articleRepository->findOnePublishedBySiteLocaleAndSlug($site, $locale, $slug);
        if ($article === null) {
            throw new NotFoundHttpException();
        }

        return $this->render('shop/blog/show.html.twig', $this->blogViewData($site, $locale, [
            'article' => $article,
        ]));
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function blogViewData(\App\Entity\Site $site, \App\Entity\Locale $locale, array $extra = []): array
    {
        return array_merge([
            'blogCategories' => $this->categoryRepository->findEnabledBySiteAndLocale($site, $locale),
            'blogMonths' => $this->monthLabels(
                $this->articleRepository->findPublishedMonthBuckets($site, $locale)
            ),
            'searchQuery' => '',
        ], $extra);
    }

    /**
     * @param list<BlogArticle> $articles
     *
     * @return list<array{year: int, month: int, label: string, articles: list<BlogArticle>}>
     */
    private function groupArticlesByMonth(array $articles): array
    {
        $groups = [];
        foreach ($articles as $article) {
            $publishedAt = $article->getPublishedAt() ?? new \DateTimeImmutable();
            $year = (int) $publishedAt->format('Y');
            $month = (int) $publishedAt->format('n');
            $key = sprintf('%04d-%02d', $year, $month);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'year' => $year,
                    'month' => $month,
                    'label' => $this->monthName($month).' '.$year,
                    'articles' => [],
                ];
            }
            $groups[$key]['articles'][] = $article;
        }

        return array_values($groups);
    }

    /**
     * @param list<array{year: int, month: int, count: int}> $buckets
     *
     * @return list<array{year: int, month: int, count: int, label: string}>
     */
    private function monthLabels(array $buckets): array
    {
        $items = [];
        foreach ($buckets as $bucket) {
            $items[] = [
                'year' => $bucket['year'],
                'month' => $bucket['month'],
                'count' => $bucket['count'],
                'label' => $this->monthName($bucket['month']).' '.$bucket['year'],
            ];
        }

        return $items;
    }

    private function monthName(int $month): string
    {
        $key = self::MONTH_KEYS[$month] ?? 'january';

        return $this->translator->trans('shop.blog.month.'.$key, [], 'messages');
    }

    /**
     * @return array{0: \App\Entity\Site, 1: \App\Entity\Locale}
     */
    private function resolveSiteLocale(Request $request, string $localeCode): array
    {
        $site = $this->siteRepository->findOneBy(['domain' => $request->getHost()]);
        $locale = $this->localeRepository->findOneBy(['code' => $localeCode]);
        if ($site === null || $locale === null) {
            throw new NotFoundHttpException();
        }

        return [$site, $locale];
    }
}
