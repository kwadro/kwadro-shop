<?php

namespace App\Controller\Shop;

use App\Repository\BlogArticleRepository;
use App\Repository\LocaleRepository;
use App\Repository\SiteRepository;
use App\Routing\ShopRoutes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends AbstractController
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly LocaleRepository $localeRepository,
        private readonly BlogArticleRepository $articleRepository,
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

        return $this->render('shop/blog/index.html.twig', [
            'articles' => $this->articleRepository->findPublishedBySiteAndLocale($site, $locale),
        ]);
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

        return $this->render('shop/blog/show.html.twig', [
            'article' => $article,
        ]);
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
