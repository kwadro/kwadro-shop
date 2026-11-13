<?php

namespace App\Service\Feed;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductFeedSetting;
use App\Repository\ProductRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds a Google Merchant Center product feed (RSS 2.0 + g: namespace).
 *
 * @see https://support.google.com/merchants/answer/7052112
 */
final class GoogleMerchantProductFeedBuilder
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly FacebookProductFeedBuilder $facebookFeedBuilder,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $defaultUri,
        #[Autowire('%app.domain%')]
        private readonly string $appDomain,
    ) {
    }

    public function build(ProductFeedSetting $setting): string
    {
        $baseUrl = $this->resolveBaseUrl($setting);
        $this->configureRouter($baseUrl);

        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('rss');
        $writer->writeAttribute('version', '2.0');
        $writer->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');

        $writer->startElement('channel');
        $channelTitle = $this->channelTitle($setting, $baseUrl);
        $writer->writeElement('title', $channelTitle);
        $writer->writeElement('link', $baseUrl);
        $writer->writeElement('description', $channelTitle.' product feed');

        foreach ($this->productRepository->findForProductFeed() as $product) {
            $this->writeItem($writer, $product, $setting, $baseUrl);
        }

        $writer->endElement(); // channel
        $writer->endElement(); // rss
        $writer->endDocument();

        return (string) $writer->outputMemory();
    }

    private function writeItem(
        \XMLWriter $writer,
        Product $product,
        ProductFeedSetting $setting,
        string $baseUrl,
    ): void {
        if (!$product->isAvailableForSale()) {
            return;
        }

        $offer = $product->getLowestAvailableOffer();
        if ($offer === null) {
            return;
        }

        $images = $product->getGalleryForCatalog();
        $imageLink = $images[0]['full'] ?? '';
        if ($imageLink === '') {
            return;
        }

        $title = $this->clip($product->getName(), 150);
        $description = $this->clip($this->plainDescription($product), 5000);
        if ($title === '' || $description === '') {
            return;
        }

        $feedCategory = $this->facebookFeedBuilder->applySettingFallbacks(
            $this->resolveFeedCategory($product),
            $setting,
        );

        $brand = $this->clip(
            $product->getBrand() ?: ($setting->getDefaultBrand() ?: 'Kvadro'),
            70,
        );
        $price = $this->money($offer->getPrice(), $setting->getCurrency());
        $salePrice = '';
        $oldPrice = $offer->getOldPrice();
        if ($oldPrice !== null && $oldPrice > $offer->getPrice()) {
            $salePrice = $price;
            $price = $this->money($oldPrice, $setting->getCurrency());
        }

        $link = $this->urlGenerator->generate('shop_product', [
            '_locale' => $setting->getLocaleCode(),
            'slug' => $product->getSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $writer->startElement('item');
        $this->writeG($writer, 'id', (string) ($product->getSku() !== '' ? $product->getSku() : $product->getId()));
        $this->writeG($writer, 'title', $title);
        $this->writeG($writer, 'description', $description);
        $this->writeG($writer, 'link', $link);
        $this->writeG($writer, 'image_link', $this->absoluteUrl($baseUrl, $imageLink));

        foreach (array_slice($images, 1, 10) as $image) {
            $url = $this->absoluteUrl($baseUrl, (string) ($image['full'] ?? ''));
            if ($url !== '') {
                $this->writeG($writer, 'additional_image_link', $url);
            }
        }

        $this->writeG($writer, 'availability', 'in_stock');
        $this->writeG($writer, 'price', $price);
        if ($salePrice !== '') {
            $this->writeG($writer, 'sale_price', $salePrice);
        }
        $this->writeG($writer, 'condition', $setting->getItemCondition());
        $this->writeG($writer, 'brand', $brand);

        if ($feedCategory['google'] !== '') {
            $this->writeG($writer, 'google_product_category', $feedCategory['google']);
        }
        if ($feedCategory['product_type'] !== '') {
            $this->writeG($writer, 'product_type', $feedCategory['product_type']);
        }

        $mpn = trim((string) ($product->getModel() ?? ''));
        if ($mpn !== '') {
            $this->writeG($writer, 'mpn', $this->clip($mpn, 70));
        } else {
            $this->writeG($writer, 'identifier_exists', 'no');
        }

        $writer->endElement(); // item
    }

    private function writeG(\XMLWriter $writer, string $name, string $value): void
    {
        $writer->startElement('g:'.$name);
        $writer->text($value);
        $writer->endElement();
    }

    /**
     * @return array
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

    private function channelTitle(ProductFeedSetting $setting, string $baseUrl): string
    {
        $domain = trim((string) ($setting->getSite()?->getDomain() ?? ''));
        if ($domain !== '') {
            $host = preg_replace('#^https?://#i', '', $domain) ?? $domain;

            return $host !== '' ? $host : 'Kvadro';
        }

        $parts = parse_url($baseUrl);
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';

        return $host !== '' ? $host : 'Kvadro';
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
