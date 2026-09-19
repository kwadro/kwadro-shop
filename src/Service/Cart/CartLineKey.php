<?php

namespace App\Service\Cart;

final class CartLineKey
{
    /** @param array<string, mixed> $product */
    public static function fromProduct(array $product): string
    {
        $supplier = \is_array($product['supplier'] ?? null) ? $product['supplier'] : [];
        $supplierId = (int) ($supplier['id'] ?? 0);
        $sku = trim((string) ($product['offerSku'] ?? $product['sku'] ?? ''));

        return self::build($supplierId, $sku);
    }

    public static function build(int $supplierId, string $sku): string
    {
        return $supplierId . ':' . $sku;
    }

    /** @param array<string, mixed> $snapshot */
    public static function fromSnapshot(array $snapshot): string
    {
        if (isset($snapshot['cartLineKey']) && \is_string($snapshot['cartLineKey']) && $snapshot['cartLineKey'] !== '') {
            return $snapshot['cartLineKey'];
        }

        return self::fromProduct($snapshot);
    }

    /** @param array<string, mixed> $product */
    public static function enrichProductSnapshot(array $product): array
    {
        $product['cartLineKey'] = self::fromProduct($product);

        return $product;
    }
}
