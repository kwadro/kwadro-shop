<?php

namespace App\Service\Cart;

use App\Service\Product\SkuDisplayFormatter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Shapes the cart summary into the JSON payload consumed by the mini cart (public/js/shop-cart.js).
 */
final class CartSummaryFormatter
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SkuDisplayFormatter $skuFormatter,
    ) {
    }

    /**
     * @param array{hasItems: bool, itemCount: int, items: list<array<string, mixed>>, total: float} $summary
     *
     * @return array<string, mixed>
     */
    public function format(array $summary): array
    {
        $items = [];
        foreach ($summary['items'] as $item) {
            $items[] = [
                'cart_item_id' => $item['cart_item_id'],
                'product_id' => $item['product_id'],
                'offer_id' => $item['offer_id'] ?? null,
                'supplier_name' => $item['supplier_name'] ?? null,
                'name' => $item['name'],
                'url' => $this->productUrl($item['snapshot'] ?? null),
                'sku' => $this->skuFormatter->format($item['sku'] ?? null),
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'price_formatted' => $this->formatPrice((float) $item['price']),
                'line_total' => $item['line_total'],
                'line_total_formatted' => $this->formatPrice((float) $item['line_total']),
                'image' => $item['image'],
            ];
        }

        return [
            'hasItems' => $summary['hasItems'],
            'itemCount' => $summary['itemCount'],
            'items' => $items,
            'total' => $summary['total'],
            'total_formatted' => $this->formatPrice($summary['total']),
        ];
    }

    private function productUrl(mixed $snapshot): ?string
    {
        $slug = \is_array($snapshot) ? trim((string) ($snapshot['slug'] ?? '')) : '';

        return $slug !== '' ? $this->urlGenerator->generate('shop_product', ['slug' => $slug]) : null;
    }

    public function formatPrice(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' ₴';
    }
}
