<?php

namespace App\Service\Feed;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductFeedSetting;
use App\Repository\ProductRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds a Meta Commerce / Facebook Catalog product feed (CSV or TSV).
 *
 * @see https://developers.facebook.com/docs/marketing-api/catalog/reference
 */
final class FacebookProductFeedBuilder
{
    public const HEADERS = [
        'id',
        'title',
        'description',
        'availability',
        'condition',
        'price',
        'link',
        'image_link',
        'brand',
        'google_product_category',
        'fb_product_category',
        'product_type',
        'additional_image_link',
        'sale_price',
        'quantity_to_sell_on_facebook',
    ];

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $defaultUri,
        #[Autowire('%app.domain%')]
        private readonly string $appDomain,
    ) {
    }

    public function build(ProductFeedSetting $setting, ?string $format = null): string
    {
        $format = $format === ProductFeedSetting::FORMAT_TSV
            ? ProductFeedSetting::FORMAT_TSV
            : ($format === ProductFeedSetting::FORMAT_CSV
                ? ProductFeedSetting::FORMAT_CSV
                : $setting->getFormat());
        $delimiter = $format === ProductFeedSetting::FORMAT_TSV ? "\t" : ',';
        $baseUrl = $this->resolveBaseUrl($setting);
        $this->configureRouter($baseUrl);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open temporary feed buffer.');
        }

        fputcsv($handle, self::HEADERS, $delimiter);
        foreach ($this->productRepository->findForProductFeed() as $product) {
            $row = $this->mapProduct($product, $setting, $baseUrl);
            if ($row === null) {
                continue;
            }
            fputcsv($handle, $row, $delimiter);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }

    /**
     * @return list<string>|null
     */
    private function mapProduct(Product $product, ProductFeedSetting $setting, string $baseUrl): ?array
    {
        if (!$product->isAvailableForSale()) {
            return null;
        }

        $offer = $product->getLowestAvailableOffer();
        if ($offer === null) {
            return null;
        }

        $feedCategory = $this->applySettingFallbacks($this->resolveFeedCategory($product), $setting);
        $images = $product->getGalleryForCatalog();
        $imageLink = $images[0]['full'] ?? '';
        if ($imageLink === '') {
            return null;
        }

        $title = $this->clip($product->getName(), 200);
        $description = $this->clip($this->plainDescription($product), 9999);
        if ($title === '' || $description === '') {
            return null;
        }

        $brand = $this->clip(
            $product->getBrand() ?: ($setting->getDefaultBrand() ?: 'Kvadro'),
            100,
        );
        $price = $this->money($offer->getPrice(), $setting->getCurrency());
        $salePrice = '';
        $oldPrice = $offer->getOldPrice();
        if ($oldPrice !== null && $oldPrice > $offer->getPrice()) {
            // Facebook sale_price is the discounted price; price stays the regular one.
            $salePrice = $price;
            $price = $this->money($oldPrice, $setting->getCurrency());
        }

        $additional = [];
        foreach (array_slice($images, 1, 10) as $image) {
            $url = $this->absoluteUrl($baseUrl, (string) ($image['full'] ?? ''));
            if ($url !== '') {
                $additional[] = $url;
            }
        }

        $link = $this->urlGenerator->generate('shop_product', [
            '_locale' => $setting->getLocaleCode(),
            'slug' => $product->getSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return [
            (string) ($product->getSku() !== '' ? $product->getSku() : $product->getId()),
            $title,
            $description,
            'in stock',
            $setting->getItemCondition(),
            $price,
            $link,
            $this->absoluteUrl($baseUrl, $imageLink),
            $brand,
            $feedCategory['google'],
            $feedCategory['fb'],
            $feedCategory['product_type'],
            implode(',', $additional),
            $salePrice,
            (string) max(1, $product->getStockQty() > 0 ? $product->getStockQty() : $offer->getQty()),
        ];
    }

    /**
     * @return array{google: string, fb: string, product_type: string}
     */
    private function resolveFeedCategory(Product $product): array
    {
        $google = '';
        $fb = '';
        $types = [];

        foreach ($product->getCategories() as $category) {
            if (!$category instanceof Category || !$category->isPublishToFeed() || !$category->isEnabled()) {
                continue;
            }

            if ($google === '' && $category->getGoogleProductCategory()) {
                $google = (string) $category->getGoogleProductCategory();
            }
            if ($fb === '' && $category->getFbProductCategory()) {
                $fb = (string) $category->getFbProductCategory();
            }
            $types[] = $this->categoryPath($category);
        }

        usort($types, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return [
            'google' => $google,
            'fb' => $fb,
            'product_type' => $types[0] ?? '',
        ];
    }

    /**
     * Apply setting fallbacks after category resolution.
     *
     * @param array{google: string, fb: string, product_type: string} $resolved
     *
     * @return array{google: string, fb: string, product_type: string}
     */
    public function applySettingFallbacks(array $resolved, ProductFeedSetting $setting): array
    {
        if ($resolved['google'] === '' && $setting->getDefaultGoogleProductCategory()) {
            $resolved['google'] = (string) $setting->getDefaultGoogleProductCategory();
        }
        if ($resolved['fb'] === '' && $setting->getDefaultFbProductCategory()) {
            $resolved['fb'] = (string) $setting->getDefaultFbProductCategory();
        }

        return $resolved;
    }

    private function categoryPath(Category $category): string
    {
        $parts = [];
        $current = $category;
        $guard = 0;
        while ($current instanceof Category && $guard < 20) {
            $name = trim($current->getName());
            if ($name !== '' && !$current->isDefault()) {
                array_unshift($parts, $name);
            }
            $current = $current->getParent();
            ++$guard;
        }

        return implode(' > ', $parts);
    }

    private function plainDescription(Product $product): string
    {
        $html = (string) ($product->getDescription() ?: $product->getShortDescription() ?: $product->getName());
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function money(float $amount, string $currency): string
    {
        return number_format($amount, 2, '.', '').' '.strtoupper($currency);
    }

    private function absoluteUrl(string $baseUrl, string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
            $path = rtrim($baseUrl, '/').'/'.ltrim($path, '/');
        }

        return $this->encodeAbsoluteUrl($path);
    }

    /**
     * Encode path segments so Facebook can fetch image URLs with spaces or parentheses.
     */
    private function encodeAbsoluteUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $path = $parts['path'] ?? '';
        $encodedPath = implode('/', array_map(
            static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
            explode('/', $path),
        ));

        $result = $parts['scheme'].'://'.$parts['host'];
        if (isset($parts['port'])) {
            $result .= ':'.$parts['port'];
        }
        $result .= $encodedPath;
        if (isset($parts['query'])) {
            $result .= '?'.$parts['query'];
        }

        return $result;
    }

    private function resolveBaseUrl(ProductFeedSetting $setting): string
    {
        $domain = trim((string) ($setting->getSite()?->getDomain() ?? ''));
        if ($domain === '') {
            $domain = trim($this->appDomain);
        }
        if ($domain === '') {
            return rtrim($this->defaultUri, '/');
        }
        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return rtrim($domain, '/');
        }

        return 'https://'.ltrim($domain, '/');
    }

    private function configureRouter(string $baseUrl): void
    {
        $parts = parse_url($baseUrl);
        if (!is_array($parts)) {
            return;
        }

        $context = $this->urlGenerator->getContext();
        if (!empty($parts['scheme'])) {
            $context->setScheme((string) $parts['scheme']);
        }
        if (!empty($parts['host'])) {
            $context->setHost((string) $parts['host']);
        }
        if (!empty($parts['port'])) {
            if (($parts['scheme'] ?? '') === 'https') {
                $context->setHttpsPort((int) $parts['port']);
            } else {
                $context->setHttpPort((int) $parts['port']);
            }
        }
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $max));
    }
}
