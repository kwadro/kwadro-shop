<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\Supplier;
use App\Repository\ProductRepository;
use App\Repository\SiteRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductCatalog
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly SiteRepository $siteRepository,
        private readonly RequestStack $requestStack,
        private readonly CustomerPriceApplier $customerPriceApplier,
    ) {
    }

    /** @return array|null */
    public function getFeaturedProduct(): ?array
    {
        $product = $this->productRepository->findFeatured($this->resolveFeatureId());

        return $product !== null
            ? $this->customerPriceApplier->applyToCatalogItem($product->toCatalogArray())
            : null;
    }

    /** @param array|null $onlyIds */
    public function getHomepageProducts(int $limit = 5, ?array $onlyIds = null): array
    {
        $products = $this->productRepository->findHomepageProducts($limit, $onlyIds);

        return $this->customerPriceApplier->applyToCatalogItems(array_map(
            static fn ($product): array => $product->toCatalogArray(),
            $products,
        ));
    }

    /** @return array|null */
    public function findById(int $id): ?array
    {
        $product = $this->productRepository->findOneWithOffers($id);

        return $product !== null
            ? $this->customerPriceApplier->applyToCatalogItem($product->toCatalogArray())
            : null;
    }

    /** @return array|null */
    public function findBySlug(string $slug): ?array
    {
        $product = $this->productRepository->findOneBySlugWithOffers($slug);

        return $product !== null
            ? $this->customerPriceApplier->applyToCatalogItem($product->toCatalogArray())
            : null;
    }

    public function findByCategory(Category $category): array
    {
        $products = $this->productRepository->findByCategoryWithOffers($category);

        return $this->customerPriceApplier->applyToCatalogItems(array_map(
            static fn ($product): array => $product->toCatalogArray(),
            $products,
        ));
    }

    public function findBySupplier(Supplier $supplier): array
    {
        $products = $this->productRepository->findBySupplierWithOffers($supplier);

        return $this->customerPriceApplier->applyToCatalogItems(array_map(
            static fn ($product): array => $product->toCatalogArray(),
            $products,
        ));
    }

    /** @param array $product */
    public function applyOffer(array $product, int $offerId): array
    {
        foreach ($product['offers'] ?? [] as $offer) {
            if (!\is_array($offer) || (int) ($offer['id'] ?? 0) !== $offerId) {
                continue;
            }

            $product['price'] = $offer['price'];
            $product['rrp'] = $offer['rrp'] ?? null;
            $product['oldPrice'] = $offer['oldPrice'] ?? null;
            $product['discountPercent'] = $offer['discountPercent'] ?? null;
            $product['member_discount_percent'] = $offer['member_discount_percent'] ?? ($product['member_discount_percent'] ?? null);
            $product['selectedOfferId'] = $offerId;
            $product['supplier'] = $offer['supplier'] ?? null;
            $product['offerSku'] = $offer['sku'] ?? null;
            $product['stockQty'] = max(0, (int) ($offer['qty'] ?? 0));
            $product['inStock'] = ($product['productInStock'] ?? true) && $product['stockQty'] > 0;

            return $product;
        }

        return $product;
    }

    private function resolveFeatureId(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        $host = $request?->getHost();
        if ($host === null || $host === '') {
            return null;
        }

        $site = $this->siteRepository->findOneBy(['domain' => $host]);

        return $site?->getFeatureId();
    }
}
