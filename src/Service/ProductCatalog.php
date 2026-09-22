<?php

namespace App\Service;

use App\Entity\Category;
use App\Repository\ProductRepository;

class ProductCatalog
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function getFeaturedProduct(): ?array
    {
        $product = $this->productRepository->findFeatured();

        return $product?->toCatalogArray();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $product = $this->productRepository->findOneWithOffers($id);

        return $product?->toCatalogArray();
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $product = $this->productRepository->findOneBySlugWithOffers($slug);

        return $product?->toCatalogArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByCategory(Category $category): array
    {
        $products = $this->productRepository->findByCategoryWithOffers($category);

        return array_map(
            static fn ($product): array => $product->toCatalogArray(),
            $products,
        );
    }

    /** @param array<string, mixed> $product */
    public function applyOffer(array $product, int $offerId): array
    {
        foreach ($product['offers'] ?? [] as $offer) {
            if (!\is_array($offer) || (int) ($offer['id'] ?? 0) !== $offerId) {
                continue;
            }

            $product['price'] = $offer['price'];
            $product['oldPrice'] = $offer['oldPrice'] ?? null;
            $product['discountPercent'] = $offer['discountPercent'] ?? null;
            $product['selectedOfferId'] = $offerId;
            $product['supplier'] = $offer['supplier'] ?? null;
            $product['offerSku'] = $offer['sku'] ?? null;
            $product['stockQty'] = max(0, (int) ($offer['qty'] ?? 0));
            $product['inStock'] = ($product['productInStock'] ?? true) && $product['stockQty'] > 0;

            return $product;
        }

        return $product;
    }
}
