<?php

namespace App\Service\Marketing;

use App\Entity\Product;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds Facebook-ready product posts from catalog data (no external AI API).
 */
final class ProductFacebookPostGenerator
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%app.domain%')]
        private readonly string $appDomain = '',
    ) {
    }

    public function generate(Product $product, string $locale = 'uk'): string
    {
        $name = trim($product->getName());
        $price = $product->getLowestPrice();
        $priceLine = $price !== null
            ? '💰 Ціна: '.number_format((float) $price, 0, '.', ' ').' ₴'
            : '💰 Ціна: уточнюйте';

        $bullets = $this->buildBullets($product);
        $stock = $product->isInStock() || $product->isAvailableForSale()
            ? '✅ В наявності'
            : '⏳ Під замовлення';

        $url = $this->resolveProductUrl($product, $locale);

        $lines = [
            $this->headline($product, $name),
            $priceLine,
            '',
        ];

        foreach ($bullets as $bullet) {
            $lines[] = '✅ '.$bullet;
        }

        $lines[] = '';
        $lines[] = $stock;
        $lines[] = '';
        $lines[] = 'Замовляйте: 👉 '.$url;
        $lines[] = '';
        $lines[] = $this->hashtags($product);

        return implode("\n", $lines);
    }

    private function headline(Product $product, string $name): string
    {
        $brand = trim((string) $product->getBrand());
        $model = trim((string) $product->getModel());
        if ($brand !== '' && $model !== '' && !str_contains(mb_strtolower($name), mb_strtolower($brand))) {
            return sprintf('%s %s — %s.', $this->shortName($name), $brand, $this->tagline($product));
        }

        return sprintf('%s — %s.', $this->shortName($name), $this->tagline($product));
    }

    private function shortName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if (mb_strlen($name) <= 90) {
            return $name;
        }

        return rtrim(mb_substr($name, 0, 87)).'…';
    }

    private function tagline(Product $product): string
    {
        $attrs = $product->getAttributesCatalogArray();
        foreach ($attrs as $attr) {
            $n = mb_strtolower((string) ($attr['name'] ?? ''));
            $v = trim((string) ($attr['value'] ?? ''));
            if ($v === '') {
                continue;
            }
            if (str_contains($n, 'матеріал') || str_contains($n, 'material')) {
                return 'якісний '.mb_strtolower($v).' для дому';
            }
            if (str_contains($n, 'секц') || str_contains($n, 'section')) {
                return $v.' секції для зручного сервірування';
            }
        }

        $type = trim((string) $product->getType());
        if ($type !== '') {
            return 'практичний вибір для дому';
        }

        return 'зручно замовити в KVADRO';
    }

    /** @return list<string> */
    private function buildBullets(Product $product): array
    {
        $bullets = [];
        foreach ($product->getFeatures() as $feature) {
            $feature = trim(strip_tags((string) $feature));
            if ($feature === '') {
                continue;
            }
            $bullets[] = $this->clip($feature, 90);
            if (\count($bullets) >= 3) {
                return $bullets;
            }
        }

        foreach ($product->getAttributesCatalogArray() as $attr) {
            $name = trim((string) ($attr['name'] ?? ''));
            $value = trim((string) ($attr['value'] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (!preg_match('/матеріал|розмір|довжин|ширин|секц|форм|виробник|material|size|section|brand|model/u', $key)) {
                continue;
            }
            $bullets[] = $this->clip($name.': '.$value, 90);
            if (\count($bullets) >= 3) {
                break;
            }
        }

        if ($bullets === []) {
            $brand = trim((string) $product->getBrand());
            if ($brand !== '') {
                $bullets[] = 'Виробник: '.$brand;
            }
            $model = trim((string) $product->getModel());
            if ($model !== '') {
                $bullets[] = 'Модель: '.$model;
            }
            $sku = trim($product->getSku());
            if ($sku !== '') {
                $bullets[] = 'Артикул: '.$sku;
            }
        }

        return \array_slice($bullets, 0, 3);
    }

    private function hashtags(Product $product): string
    {
        $tags = ['KVADRO', 'ІваноФранківськ', 'товаридлядому'];
        $brand = preg_replace('/\s+/u', '', (string) $product->getBrand()) ?: '';
        $model = preg_replace('/[^A-Za-z0-9А-Яа-яІіЇїЄєҐґ_-]+/u', '', (string) $product->getModel()) ?: '';
        $sku = preg_replace('/[^A-Za-z0-9_-]+/', '', $product->getSku()) ?: '';
        if ($brand !== '') {
            array_unshift($tags, $brand);
        }
        if ($model !== '') {
            array_unshift($tags, $model);
        } elseif ($sku !== '') {
            array_unshift($tags, $sku);
        }

        $categories = $product->getCategories();
        foreach ($categories as $category) {
            $label = preg_replace('/\s+/u', '', $category->getName()) ?: '';
            $label = preg_replace('/[^\p{L}\p{N}_-]+/u', '', $label) ?: '';
            if ($label !== '' && \count($tags) < 8) {
                $tags[] = $label;
            }
        }

        $tags = array_values(array_unique(array_filter($tags)));

        return implode(' ', array_map(static fn (string $t): string => '#'.$t, \array_slice($tags, 0, 8)));
    }

    private function clip(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $max - 1)).'…';
    }

    private function resolveProductUrl(Product $product, string $locale): string
    {
        $path = $this->urlGenerator->generate('shop_product', [
            '_locale' => $locale,
            'slug' => $product->getSlug(),
        ]);

        $domain = trim($this->appDomain);
        if ($domain !== '') {
            $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
            $scheme = str_contains($domain, 'localhost') || str_ends_with($domain, '.local') ? 'http' : 'https';

            return $scheme.'://'.rtrim($domain, '/').$path;
        }

        return $this->urlGenerator->generate('shop_product', [
            '_locale' => $locale,
            'slug' => $product->getSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
