<?php

namespace App\Twig;

use App\Entity\BlogArticle;
use App\Entity\Category;
use App\Service\Seo\JsonLdFactory;
use App\Service\SiteSettingsProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class JsonLdExtension extends AbstractExtension
{
    public function __construct(
        private readonly JsonLdFactory $jsonLdFactory,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly SiteSettingsProvider $siteSettingsProvider,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('json_ld', [$this, 'renderJsonLd'], ['is_safe' => ['html']]),
            new TwigFunction('product_json_ld', [$this, 'productJsonLd'], ['is_safe' => ['html']]),
            new TwigFunction('product_category_json_ld', [$this, 'productCategoryJsonLd'], ['is_safe' => ['html']]),
            new TwigFunction('blog_article_json_ld', [$this, 'blogArticleJsonLd'], ['is_safe' => ['html']]),
            new TwigFunction('blog_listing_json_ld', [$this, 'blogListingJsonLd'], ['is_safe' => ['html']]),
            new TwigFunction('home_json_ld', [$this, 'homeJsonLd'], ['is_safe' => ['html']]),
        ];
    }

    /** @param array<string, mixed> $data */
    public function renderJsonLd(array $data): string
    {
        if ($data === []) {
            return '';
        }

        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS,
        );

        if ($json === false) {
            return '';
        }

        return '<script type="application/ld+json">'.$json.'</script>';
    }

    /**
     * @param array<string, mixed> $product
     * @param list<array{name: string, url: string}>|null $breadcrumbs
     */
    public function productJsonLd(array $product, ?array $breadcrumbs = null): string
    {
        $locale = $this->locale();
        $crumbs = $breadcrumbs ?? $this->defaultProductBreadcrumbs($product, $locale);

        return $this->renderJsonLd(
            $this->jsonLdFactory->productPage($product, $locale, $crumbs, $this->organization()),
        );
    }

    /**
     * @param list<array<string, mixed>> $products
     * @param list<array{name: string, url: string}>|null $breadcrumbs
     */
    public function productCategoryJsonLd(Category $category, array $products, int $total = 0, ?array $breadcrumbs = null): string
    {
        $locale = $this->locale();
        $crumbs = $breadcrumbs ?? $this->defaultCategoryBreadcrumbs($category, $locale);

        return $this->renderJsonLd(
            $this->jsonLdFactory->productCategoryPage(
                $category,
                $locale,
                $products,
                $total,
                $crumbs,
                $this->organization(),
            ),
        );
    }

    /** @param list<array{name: string, url: string}>|null $breadcrumbs */
    public function blogArticleJsonLd(BlogArticle $article, ?array $breadcrumbs = null): string
    {
        $locale = $this->locale();
        $crumbs = $breadcrumbs ?? [
            [
                'name' => $this->translator->trans('shop.category.home', [], 'messages'),
                'url' => $this->urlGenerator->generate('shop_home', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            [
                'name' => $this->translator->trans('shop.blog.title', [], 'messages'),
                'url' => $this->urlGenerator->generate('shop_blog_index', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            [
                'name' => $article->getTitle(),
                'url' => $this->urlGenerator->generate('shop_blog_article', ['_locale' => $locale, 'slug' => $article->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        ];

        return $this->renderJsonLd(
            $this->jsonLdFactory->blogArticlePage($article, $locale, $crumbs, $this->organization()),
        );
    }

    /**
     * @param list<BlogArticle> $articles
     * @param list<array{name: string, url: string}> $breadcrumbs
     */
    public function blogListingJsonLd(string $name, string $url, string $description, array $articles, array $breadcrumbs): string
    {
        return $this->renderJsonLd(
            $this->jsonLdFactory->blogListingPage(
                $name,
                $url,
                $description,
                $articles,
                $this->locale(),
                $breadcrumbs,
                $this->organization(),
            ),
        );
    }

    /**
     * @param list<array{name?: string, slug?: string, url?: string}>|null $categories
     */
    public function homeJsonLd(?array $categories = null): string
    {
        $locale = $this->locale();
        $request = $this->requestStack->getCurrentRequest();
        $settings = $request
            ? $this->siteSettingsProvider->getSettings($request->getHost(), $request->getLocale())
            : [];
        $seo = \is_array($settings['seo'] ?? null) ? $settings['seo'] : [];
        $ogFile = trim((string) ($seo['og_image'] ?? ''));
        $ogImage = $ogFile !== '' ? $this->absoluteAssetUrl('uploads/images/'.$ogFile) : null;

        $topCategories = [];
        foreach ($categories ?? [] as $category) {
            if (!\is_array($category)) {
                continue;
            }
            $name = trim((string) ($category['name'] ?? ''));
            $url = trim((string) ($category['url'] ?? ''));
            if ($url === '' && !empty($category['slug'])) {
                $url = $this->urlGenerator->generate(
                    'shop_category',
                    ['_locale' => $locale, 'slug' => (string) $category['slug']],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            }
            if ($name === '' || $url === '') {
                continue;
            }
            $topCategories[] = ['name' => $name, 'url' => $url];
        }

        return $this->renderJsonLd(
            $this->jsonLdFactory->homePage(
                $locale,
                $this->organization(),
                [
                    'title' => trim((string) ($seo['title'] ?? $seo['meta_title'] ?? $seo['og_title'] ?? '')),
                    'description' => trim((string) ($seo['meta_description'] ?? $seo['og_description'] ?? '')),
                    'ogImage' => $ogImage,
                ],
                $topCategories,
            ),
        );
    }

    /** @return array{name: string, url: string, logo: ?string, email: ?string} */
    private function organization(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $settings = $request
            ? $this->siteSettingsProvider->getSettings($request->getHost(), $request->getLocale())
            : [];
        $locale = $this->locale();
        $home = $this->urlGenerator->generate('shop_home', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL);
        $logoFile = trim((string) ($settings['header']['logo'] ?? ''));
        $logo = $logoFile !== ''
            ? $this->absoluteAssetUrl('uploads/logo/'.$logoFile)
            : null;

        return [
            'name' => trim((string) ($settings['header']['title'] ?? '')) ?: $this->translator->trans('shop.brand', [], 'messages'),
            'url' => $home,
            'logo' => $logo,
            'email' => null,
        ];
    }

    /**
     * @param array<string, mixed> $product
     *
     * @return list<array{name: string, url: string}>
     */
    private function defaultProductBreadcrumbs(array $product, string $locale): array
    {
        $crumbs = [[
            'name' => $this->translator->trans('shop.category.home', [], 'messages'),
            'url' => $this->urlGenerator->generate('shop_home', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL),
        ]];

        $categories = $product['categories'] ?? [];
        if (\is_array($categories)) {
            usort($categories, static fn (array $a, array $b): int => ((int) ($a['level'] ?? 0)) <=> ((int) ($b['level'] ?? 0)));
            foreach ($categories as $category) {
                if (!\is_array($category) || empty($category['slug']) || empty($category['name'])) {
                    continue;
                }
                $crumbs[] = [
                    'name' => (string) $category['name'],
                    'url' => $this->urlGenerator->generate(
                        'shop_category',
                        ['_locale' => $locale, 'slug' => (string) $category['slug']],
                        UrlGeneratorInterface::ABSOLUTE_URL,
                    ),
                ];
            }
        }

        $crumbs[] = [
            'name' => (string) ($product['name'] ?? ''),
            'url' => $this->urlGenerator->generate(
                'shop_product',
                ['_locale' => $locale, 'slug' => (string) ($product['slug'] ?? '')],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        ];

        return $crumbs;
    }

    /** @return list<array{name: string, url: string}> */
    private function defaultCategoryBreadcrumbs(Category $category, string $locale): array
    {
        $crumbs = [[
            'name' => $this->translator->trans('shop.category.home', [], 'messages'),
            'url' => $this->urlGenerator->generate('shop_home', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL),
        ]];

        $parent = $category->getParent();
        if ($parent !== null && !$parent->isDefault() && $parent->isEnabled()) {
            $crumbs[] = [
                'name' => $parent->getName(),
                'url' => $this->urlGenerator->generate(
                    'shop_category',
                    ['_locale' => $locale, 'slug' => $parent->getSlug()],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            ];
        }

        $crumbs[] = [
            'name' => $category->getName(),
            'url' => $this->urlGenerator->generate(
                'shop_category',
                ['_locale' => $locale, 'slug' => $category->getSlug()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        ];

        return $crumbs;
    }

    private function locale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'uk';
    }

    private function absoluteAssetUrl(string $path): string
    {
        $path = '/'.ltrim(str_replace('\\', '/', $path), '/');
        $context = $this->urlGenerator->getContext();
        $scheme = $context->getScheme() ?: 'https';
        $host = $context->getHost() ?: 'localhost';

        return $scheme.'://'.$host.$path;
    }
}
