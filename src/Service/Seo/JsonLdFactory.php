<?php

namespace App\Service\Seo;

use App\Entity\BlogArticle;
use App\Entity\Category;
use App\Service\Blog\BlogOgImagePath;
use App\Service\Product\ProductOgImagePath;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds schema.org JSON-LD graphs for shop pages.
 */
final class JsonLdFactory
{
    private const CURRENCY = 'UAH';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param array<string, mixed> $product Catalog product array
     * @param list<array{name: string, url: string}> $breadcrumbs
     * @param array{name?: string, url?: string, logo?: string|null, email?: string|null} $organization
     *
     * @return array<string, mixed>
     */
    public function productPage(array $product, string $locale, array $breadcrumbs, array $organization = []): array
    {
        $url = $this->urlGenerator->generate(
            'shop_product',
            ['_locale' => $locale, 'slug' => (string) ($product['slug'] ?? '')],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $description = $this->plainText((string) ($product['metaDescription'] ?? ''));
        if ($description === '') {
            $description = $this->plainText((string) ($product['shortDescription'] ?? ''));
        }
        if ($description === '') {
            $description = $this->plainText((string) ($product['description'] ?? ''));
        }

        $images = $this->productImages($product);
        $brandName = trim((string) ($product['brand'] ?? ''));
        $sku = trim((string) ($product['sku'] ?? ''));

        $productNode = [
            '@type' => 'Product',
            '@id' => $url.'#product',
            'name' => (string) ($product['name'] ?? ''),
            'description' => $description !== '' ? $description : null,
            'sku' => $sku !== '' ? $sku : null,
            'mpn' => $sku !== '' ? $sku : null,
            'image' => $images !== [] ? $images : null,
            'url' => $url,
            'category' => $this->productCategoryLabel($product),
            'brand' => $brandName !== '' ? [
                '@type' => 'Brand',
                'name' => $brandName,
            ] : null,
            'offers' => $this->productOffers($product, $url),
        ];

        return $this->graph([
            $this->breadcrumbList($breadcrumbs),
            $this->filterNull($productNode),
            $this->organizationNode($organization),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $products Catalog products on the current page
     * @param list<array{name: string, url: string}> $breadcrumbs
     * @param array{name?: string, url?: string, logo?: string|null, email?: string|null} $organization
     *
     * @return array<string, mixed>
     */
    public function productCategoryPage(
        Category $category,
        string $locale,
        array $products,
        int $total,
        array $breadcrumbs,
        array $organization = [],
    ): array {
        $url = $this->urlGenerator->generate(
            'shop_category',
            ['_locale' => $locale, 'slug' => $category->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $description = $this->plainText((string) ($category->getMetaDescription() ?? ''));
        $listItems = [];
        $position = 1;
        foreach ($products as $product) {
            if (!\is_array($product) || empty($product['slug'])) {
                continue;
            }
            $itemUrl = $this->urlGenerator->generate(
                'shop_product',
                ['_locale' => $locale, 'slug' => (string) $product['slug']],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
            $listItems[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'url' => $itemUrl,
                'name' => (string) ($product['name'] ?? ''),
            ];
        }

        $collection = $this->filterNull([
            '@type' => 'CollectionPage',
            '@id' => $url.'#webpage',
            'name' => $category->getName(),
            'description' => $description !== '' ? $description : null,
            'url' => $url,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $organization['name'] ?? null,
                'url' => $organization['url'] ?? null,
            ],
            'mainEntity' => [
                '@type' => 'ItemList',
                'name' => $category->getName(),
                'numberOfItems' => $total > 0 ? $total : \count($listItems),
                'itemListElement' => $listItems,
            ],
        ]);

        return $this->graph([
            $this->breadcrumbList($breadcrumbs),
            $collection,
            $this->organizationNode($organization),
        ]);
    }

    /**
     * @param list<array{name: string, url: string}> $breadcrumbs
     * @param array{name?: string, url?: string, logo?: string|null, email?: string|null} $organization
     *
     * @return array<string, mixed>
     */
    public function blogArticlePage(BlogArticle $article, string $locale, array $breadcrumbs, array $organization = []): array
    {
        $url = $this->urlGenerator->generate(
            'shop_blog_article',
            ['_locale' => $locale, 'slug' => $article->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $description = $this->plainText((string) ($article->getMetaDescription() ?? ''));
        if ($description === '') {
            $description = $this->plainText($article->getContent());
            if (mb_strlen($description) > 300) {
                $description = rtrim(mb_substr($description, 0, 299), " \t.,;:").'…';
            }
        }

        $image = null;
        if ($article->getOgImage()) {
            $web = BlogOgImagePath::webPath($article->getOgImage());
            $image = $web !== '' ? $this->absoluteAsset(ltrim($web, '/')) : null;
        }

        $published = $article->getPublishedAt()?->format(DATE_ATOM);
        $modified = $article->getUpdatedAt()?->format(DATE_ATOM) ?? $published;

        $org = $this->organizationNode($organization);
        $publisher = $org !== null ? array_intersect_key($org, array_flip(['@type', '@id', 'name', 'logo', 'url'])) : null;

        $articleNode = $this->filterNull([
            '@type' => 'BlogPosting',
            '@id' => $url.'#article',
            'headline' => $article->getTitle(),
            'description' => $description !== '' ? $description : null,
            'image' => $image,
            'datePublished' => $published,
            'dateModified' => $modified,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $url,
            ],
            'author' => $publisher !== null ? $publisher : [
                '@type' => 'Organization',
                'name' => $organization['name'] ?? 'Квадро',
            ],
            'publisher' => $publisher,
            'articleSection' => $this->blogArticleSections($article),
            'keywords' => $this->blogArticleKeywords($article),
            'inLanguage' => $locale,
            'url' => $url,
        ]);

        return $this->graph([
            $this->breadcrumbList($breadcrumbs),
            $articleNode,
            $org,
        ]);
    }

    /**
     * Blog index / category / archive listing.
     *
     * @param list<BlogArticle> $articles
     * @param list<array{name: string, url: string}> $breadcrumbs
     * @param array{name?: string, url?: string, logo?: string|null, email?: string|null} $organization
     *
     * @return array<string, mixed>
     */
    public function blogListingPage(
        string $name,
        string $url,
        string $description,
        array $articles,
        string $locale,
        array $breadcrumbs,
        array $organization = [],
    ): array {
        $listItems = [];
        $position = 1;
        foreach ($articles as $article) {
            if (!$article instanceof BlogArticle) {
                continue;
            }
            $itemUrl = $this->urlGenerator->generate(
                'shop_blog_article',
                ['_locale' => $locale, 'slug' => $article->getSlug()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
            $listItems[] = $this->filterNull([
                '@type' => 'ListItem',
                'position' => $position++,
                'url' => $itemUrl,
                'name' => $article->getTitle(),
                'item' => $this->filterNull([
                    '@type' => 'BlogPosting',
                    'headline' => $article->getTitle(),
                    'url' => $itemUrl,
                    'datePublished' => $article->getPublishedAt()?->format(DATE_ATOM),
                    'description' => $this->plainText((string) ($article->getMetaDescription() ?? '')) ?: null,
                ]),
            ]);
        }

        $collection = $this->filterNull([
            '@type' => 'CollectionPage',
            '@id' => $url.'#webpage',
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'url' => $url,
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => \count($listItems),
                'itemListElement' => $listItems,
            ],
        ]);

        return $this->graph([
            $this->breadcrumbList($breadcrumbs),
            $collection,
            $this->organizationNode($organization),
        ]);
    }

    /**
     * Homepage: Organization + WebSite (SearchAction) + WebPage.
     *
     * @param array{name?: string, url?: string, logo?: string|null, email?: string|null} $organization
     * @param array{title?: string, description?: string, ogImage?: string|null} $page
     * @param list<array{name: string, url: string}> $topCategories
     *
     * @return array<string, mixed>
     */
    public function homePage(string $locale, array $organization = [], array $page = [], array $topCategories = []): array
    {
        $homeUrl = $this->urlGenerator->generate(
            'shop_home',
            ['_locale' => $locale],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $searchUrl = $this->urlGenerator->generate(
            'shop_search',
            ['_locale' => $locale, 'q' => 'search_term_string'],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        // Keep a template URL for SearchAction.
        $searchTemplate = preg_replace('/search_term_string/', '{search_term_string}', $searchUrl) ?? $searchUrl;

        $org = $this->organizationNode($organization);
        $name = trim((string) ($page['title'] ?? $organization['name'] ?? ''));
        $description = $this->plainText((string) ($page['description'] ?? ''));
        $ogImage = trim((string) ($page['ogImage'] ?? ''));

        $website = $this->filterNull([
            '@type' => 'WebSite',
            '@id' => $homeUrl.'#website',
            'url' => $homeUrl,
            'name' => $name !== '' ? $name : null,
            'description' => $description !== '' ? $description : null,
            'inLanguage' => $locale,
            'publisher' => $org !== null ? ['@id' => $org['@id'] ?? ($homeUrl.'#organization')] : null,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $searchTemplate,
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ]);

        $webPage = $this->filterNull([
            '@type' => 'WebPage',
            '@id' => $homeUrl.'#webpage',
            'url' => $homeUrl,
            'name' => $name !== '' ? $name : null,
            'description' => $description !== '' ? $description : null,
            'isPartOf' => ['@id' => $homeUrl.'#website'],
            'about' => $org !== null ? ['@id' => $org['@id'] ?? ($homeUrl.'#organization')] : null,
            'primaryImageOfPage' => $ogImage !== '' ? [
                '@type' => 'ImageObject',
                'url' => $ogImage,
            ] : null,
            'inLanguage' => $locale,
        ]);

        $itemList = null;
        if ($topCategories !== []) {
            $elements = [];
            $position = 1;
            foreach ($topCategories as $category) {
                $catName = trim((string) ($category['name'] ?? ''));
                $catUrl = trim((string) ($category['url'] ?? ''));
                if ($catName === '' || $catUrl === '') {
                    continue;
                }
                $elements[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $catName,
                    'url' => $catUrl,
                ];
            }
            if ($elements !== []) {
                $itemList = [
                    '@type' => 'ItemList',
                    '@id' => $homeUrl.'#categories',
                    'name' => 'Категорії',
                    'itemListElement' => $elements,
                ];
            }
        }

        return $this->graph([
            $org,
            $website,
            $webPage,
            $itemList,
        ]);
    }

    /**
     * @param list<array{name: string, url: string}> $items
     *
     * @return array<string, mixed>|null
     */
    public function breadcrumbList(array $items): ?array
    {
        if ($items === []) {
            return null;
        }

        $elements = [];
        $position = 1;
        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));
            if ($name === '' || $url === '') {
                continue;
            }
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $name,
                'item' => $url,
            ];
        }

        if ($elements === []) {
            return null;
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @param list<array<string, mixed>|null> $nodes
     *
     * @return array<string, mixed>
     */
    private function graph(array $nodes): array
    {
        $graph = [];
        foreach ($nodes as $node) {
            if (\is_array($node) && $node !== []) {
                $graph[] = $node;
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    /**
     * @param array{name?: string, url?: string, logo?: string|null, email?: string|null} $organization
     *
     * @return array<string, mixed>|null
     */
    private function organizationNode(array $organization): ?array
    {
        $name = trim((string) ($organization['name'] ?? ''));
        $url = trim((string) ($organization['url'] ?? ''));
        if ($name === '' && $url === '') {
            return null;
        }

        $logo = trim((string) ($organization['logo'] ?? ''));
        $email = trim((string) ($organization['email'] ?? ''));

        return $this->filterNull([
            '@type' => 'Organization',
            '@id' => ($url !== '' ? $url : '').'#organization',
            'name' => $name !== '' ? $name : null,
            'url' => $url !== '' ? $url : null,
            'email' => $email !== '' ? $email : null,
            'logo' => $logo !== '' ? [
                '@type' => 'ImageObject',
                'url' => $logo,
            ] : null,
        ]);
    }

    /**
     * @param array<string, mixed> $product
     *
     * @return list<string>
     */
    private function productImages(array $product): array
    {
        $images = [];
        $og = trim((string) ($product['ogImage'] ?? ''));
        if ($og !== '') {
            $ogWeb = ProductOgImagePath::webPath($og);
            if ($ogWeb !== '') {
                $images[] = str_starts_with($ogWeb, 'http://') || str_starts_with($ogWeb, 'https://')
                    ? $ogWeb
                    : $this->absoluteUrlPath($ogWeb);
            }
        }

        foreach ($product['gallery'] ?? [] as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $path = trim((string) ($item['full'] ?? $item['thumb'] ?? ''));
            if ($path === '') {
                continue;
            }
            $images[] = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
                ? $path
                : $this->absoluteUrlPath($path);
        }

        return array_values(array_unique($images));
    }

    /**
     * @param array<string, mixed> $product
     *
     * @return array<string, mixed>|null
     */
    private function productOffers(array $product, string $productUrl): ?array
    {
        $offers = [];
        foreach ($product['offers'] ?? [] as $offer) {
            if (!\is_array($offer) || !isset($offer['price'])) {
                continue;
            }
            $price = (float) $offer['price'];
            if ($price <= 0) {
                continue;
            }
            $inStock = !empty($offer['inStock']);
            $offers[] = $this->filterNull([
                '@type' => 'Offer',
                'url' => $productUrl,
                'priceCurrency' => self::CURRENCY,
                'price' => number_format($price, 2, '.', ''),
                'availability' => $inStock
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'sku' => trim((string) ($offer['sku'] ?? '')) ?: null,
            ]);
        }

        if ($offers === []) {
            $price = isset($product['price']) ? (float) $product['price'] : 0.0;
            if ($price <= 0) {
                return null;
            }

            return $this->filterNull([
                '@type' => 'Offer',
                'url' => $productUrl,
                'priceCurrency' => self::CURRENCY,
                'price' => number_format($price, 2, '.', ''),
                'availability' => !empty($product['inStock'])
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
            ]);
        }

        if (\count($offers) === 1) {
            return $offers[0];
        }

        $prices = array_map(static fn (array $o): float => (float) $o['price'], $offers);

        return [
            '@type' => 'AggregateOffer',
            'url' => $productUrl,
            'priceCurrency' => self::CURRENCY,
            'lowPrice' => number_format(min($prices), 2, '.', ''),
            'highPrice' => number_format(max($prices), 2, '.', ''),
            'offerCount' => \count($offers),
            'availability' => !empty($product['inStock'])
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'offers' => $offers,
        ];
    }

    /** @param array<string, mixed> $product */
    private function productCategoryLabel(array $product): ?string
    {
        $categories = $product['categories'] ?? [];
        if (\is_array($categories) && $categories !== []) {
            $names = [];
            foreach ($categories as $category) {
                if (\is_array($category) && !empty($category['name'])) {
                    $names[] = (string) $category['name'];
                }
            }
            if ($names !== []) {
                return implode(' / ', $names);
            }
        }

        $label = trim((string) ($product['category'] ?? ''));

        return $label !== '' ? $label : null;
    }

    private function blogArticleSections(BlogArticle $article): ?string
    {
        $names = [];
        foreach ($article->getCategories() as $category) {
            if ($category->isEnabled()) {
                $names[] = $category->getName();
            }
        }

        return $names !== [] ? implode(', ', $names) : null;
    }

    private function blogArticleKeywords(BlogArticle $article): ?string
    {
        $tags = $article->getTagList();
        if ($tags === []) {
            return null;
        }

        return implode(', ', $tags);
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function absoluteAsset(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return $this->absoluteUrlPath('/'.$path);
    }

    private function absoluteUrlPath(string $path): string
    {
        $path = '/'.ltrim(str_replace('\\', '/', $path), '/');
        $requestContext = $this->urlGenerator->getContext();
        $scheme = $requestContext->getScheme() ?: 'https';
        $host = $requestContext->getHost() ?: 'localhost';
        $port = '';
        $httpPort = $requestContext->getHttpPort();
        $httpsPort = $requestContext->getHttpsPort();
        if ($scheme === 'http' && $httpPort !== 80) {
            $port = ':'.$httpPort;
        } elseif ($scheme === 'https' && $httpsPort !== 443) {
            $port = ':'.$httpsPort;
        }

        return $scheme.'://'.$host.$port.$path;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function filterNull(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if (\is_array($value)) {
                $value = $this->filterNull($value);
                if ($value === []) {
                    continue;
                }
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
