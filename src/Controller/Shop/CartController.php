<?php

namespace App\Controller\Shop;

use App\Routing\ShopRoutes;
use App\Service\Cart\CartStorageService;
use App\Service\ProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    public function __construct(
        private readonly ProductCatalog $productCatalog,
        private readonly CartStorageService $cartStorage,
    ) {
    }

    #[Route(
        '/{_locale}/cart/add',
        name: 'shop_cart_add',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['POST'],
    )]
    public function add(string $_locale, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('cart_add', (string) $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $productId = (int) $request->request->get('product_id');
        $quantity = max(1, (int) $request->request->get('quantity', 1));

        $product = $this->productCatalog->findById($productId);
        if (!$product || empty($product['inStock']) || empty($product['hasPrice'])) {
            throw new NotFoundHttpException();
        }

        $offerId = (int) $request->request->get('offer_id');
        if ($offerId > 0) {
            $product = $this->productCatalog->applyOffer($product, $offerId);
            if (empty($product['inStock']) || empty($product['stockQty'])) {
                throw new NotFoundHttpException();
            }
        }

        $stockQty = max(1, (int) ($product['stockQty'] ?? 1));
        $quantity = min($quantity, $stockQty);

        $this->cartStorage->addToCart($productId, $quantity, $product);

        return new JsonResponse([
            'success' => true,
            'message' => 'shop.cart.added',
            'cart' => $this->formatCartSummary($this->cartStorage->getSummary()),
            'added_item' => $this->buildAddedItemTrackingPayload($product, $quantity),
        ]);
    }

    #[Route(
        '/{_locale}/cart/remove',
        name: 'shop_cart_remove',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['POST'],
    )]
    public function remove(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('cart_remove', (string) $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $cartItemId = (int) $request->request->get('cart_item_id');
        if ($cartItemId <= 0) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid cart item.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->cartStorage->removeItem($cartItemId)) {
            return new JsonResponse(['success' => false, 'error' => 'Item not found in cart.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'shop.cart.removed',
            'cart' => $this->formatCartSummary($this->cartStorage->getSummary()),
        ]);
    }

    /** @param array{hasItems: bool, itemCount: int, items: list<array<string, mixed>>, total: float} $summary */
    /** @return array<string, mixed> */
    private function formatCartSummary(array $summary): array
    {
        $items = [];
        foreach ($summary['items'] as $item) {
            $items[] = [
                'cart_item_id' => $item['cart_item_id'],
                'product_id' => $item['product_id'],
                'offer_id' => $item['offer_id'] ?? null,
                'supplier_name' => $item['supplier_name'] ?? null,
                'name' => $item['name'],
                'sku' => $item['sku'],
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

    private function formatPrice(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' ₴';
    }

    /** @param array<string, mixed> $product */
    /** @return array<string, mixed> */
    private function buildAddedItemTrackingPayload(array $product, int $quantity): array
    {
        $price = round((float) ($product['price'] ?? 0), 2);
        $quantity = max(1, $quantity);

        return [
            'item_id' => (string) ($product['offerSku'] ?? $product['sku'] ?? $product['id'] ?? ''),
            'item_name' => (string) ($product['name'] ?? ''),
            'item_category' => (string) ($product['category'] ?? ''),
            'price' => $price,
            'quantity' => $quantity,
            'currency' => 'UAH',
            'value' => round($price * $quantity, 2),
            'product_id' => (int) ($product['id'] ?? 0),
            'offer_id' => isset($product['selectedOfferId']) ? (int) $product['selectedOfferId'] : null,
        ];
    }
}
