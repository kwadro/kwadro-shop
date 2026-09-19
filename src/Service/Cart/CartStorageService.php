<?php

namespace App\Service\Cart;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\CartItemStatus;
use App\Entity\CartStatus;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Service\Shipment\ShipmentAddressService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class CartStorageService
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly VisitorIdResolver $visitorIdResolver,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly ShipmentAddressService $shipmentAddressService,
    ) {
    }

    public function getActiveCart(): ?Cart
    {
        return $this->getOrCreateCartEntity(false);
    }

    /** @return array<string, mixed>|null */
    public function getCartData(): ?array
    {
        $cart = $this->getOrCreateCartEntity(false);

        return $cart !== null ? $this->buildLegacyCartData($cart) : null;
    }

    /** @param array<string, mixed> $cartData */
    public function setCartData(array $cartData, bool $clearCheckout = true): void
    {
        $product = $cartData['product'] ?? null;
        if (!\is_array($product)) {
            return;
        }

        $productId = (int) ($cartData['product_id'] ?? $product['id'] ?? 0);
        $quantity = max(1, (int) ($cartData['quantity'] ?? 1));

        $this->upsertCartItem($productId, $quantity, $product, $clearCheckout);
    }

    /** @param array<string, mixed> $product */
    public function addToCart(int $productId, int $quantity, array $product): void
    {
        $quantity = max(1, $quantity);
        $product = CartLineKey::enrichProductSnapshot($product);
        $stockQty = max(1, (int) ($product['stockQty'] ?? 1));
        $cart = $this->getOrCreateCartEntity(true);
        if ($cart === null) {
            return;
        }

        $lineKey = CartLineKey::fromProduct($product);
        $existingItem = $this->findActiveItemByLineKey($cart, $lineKey);
        if ($existingItem !== null) {
            $quantity = min($existingItem->getQuantity() + $quantity, $stockQty);
            $this->syncCartItemFromProduct($existingItem, $productId, $quantity, $product);
        } else {
            $this->createCartItem($cart, $productId, min($quantity, $stockQty), $product);
            $this->clearCheckoutSessionData($cart);
        }

        $cart->setCartData(null);
        $this->entityManager->flush();
    }

    public function removeItem(int $cartItemId): bool
    {
        $cart = $this->getOrCreateCartEntity(false);
        if ($cart === null) {
            return false;
        }

        $item = $this->findActiveItemById($cart, $cartItemId);
        if ($item === null) {
            return false;
        }

        $item->setStatus(CartItemStatus::Inactive);
        $cart->setCartData(null);
        $this->clearCheckoutSessionData($cart);
        $this->entityManager->flush();

        return true;
    }

    public function deactivateActiveCart(): void
    {
        $cart = $this->getOrCreateCartEntity(false);
        if ($cart === null) {
            return;
        }

        $this->deactivateCart($cart);
    }

    public function deactivateCartForOrder(Order $order): void
    {
        $cart = $this->cartRepository->findForOrder($order->getCustomer(), $order->getVisitorId());
        if ($cart === null) {
            return;
        }

        $this->deactivateCart($cart);
    }

    /** @return array<string, mixed>|null */
    public function getContactData(): ?array
    {
        $cart = $this->getOrCreateCartEntity(false);
        if ($cart === null) {
            return $this->getContactDataFromUser($this->getAuthenticatedUser());
        }

        $contact = $cart->getContactData();
        if (\is_array($contact) && ($contact['customerName'] ?? '') !== '') {
            return $this->normalizeContactData($contact);
        }

        $legacy = $this->extractLegacyContactFromDelivery($cart->getDeliveryData());
        if (\is_array($legacy)) {
            return $legacy;
        }

        return $this->getContactDataFromUser($cart->getCustomer() ?? $this->getAuthenticatedUser());
    }

    /** @param array<string, mixed> $contactData */
    public function setContactData(array $contactData): void
    {
        $cart = $this->getOrCreateCartEntity(true);
        $normalized = $this->normalizeContactData($contactData);
        $cart->setContactData($normalized);

        $customer = $cart->getCustomer() ?? $this->getAuthenticatedUser();
        if ($customer instanceof User) {
            $customer->applyContactData($normalized);
        }

        $this->entityManager->flush();
    }

    /** @return array<string, mixed>|null */
    public function getDeliveryData(): ?array
    {
        $cart = $this->getOrCreateCartEntity(false);
        if ($cart === null) {
            return null;
        }

        $address = $cart->getShipmentAddress();
        if ($address !== null) {
            return $this->normalizeDeliveryData($address->toDeliveryArray());
        }

        return $this->normalizeDeliveryData($cart->getDeliveryData());
    }

    /** @param array<string, mixed> $deliveryData */
    public function setDeliveryData(array $deliveryData): void
    {
        $cart = $this->getOrCreateCartEntity(true);
        $normalized = $this->normalizeDeliveryData($deliveryData);
        if ($normalized === null) {
            $this->shipmentAddressService->clearCartAddress($cart);
            $this->entityManager->flush();

            return;
        }

        $address = $this->shipmentAddressService->syncCartAddress($cart, $normalized);
        $cart->setDeliveryData($address->toDeliveryArray());
        $this->entityManager->flush();
    }

    public function clearDelivery(): void
    {
        $cart = $this->getOrCreateCartEntity(false);
        if ($cart === null) {
            return;
        }

        $this->shipmentAddressService->clearCartAddress($cart);
        $cart->setOrderData(null);
        $this->entityManager->flush();
    }

    /** @return array<string, mixed>|null */
    public function getCheckoutData(): ?array
    {
        $contact = $this->getContactData();
        $delivery = $this->getDeliveryData();
        if (!\is_array($contact) || !\is_array($delivery)) {
            return null;
        }

        return array_merge($contact, $delivery);
    }

    /** @return array<string, mixed>|null */
    public function getOrderData(): ?array
    {
        $cart = $this->getOrCreateCartEntity(false);

        return $cart?->getOrderData();
    }

    /** @param array<string, mixed> $orderData */
    public function setOrderData(array $orderData): void
    {
        $cart = $this->getOrCreateCartEntity(true);
        $cart->setOrderData($orderData);
        $this->entityManager->flush();
    }

    public function updateOrderStatus(string $status): void
    {
        $order = $this->getOrderData();
        if (!\is_array($order)) {
            return;
        }

        $order['status'] = $status;
        $this->setOrderData($order);
    }

    /** @return array{hasItems: bool, itemCount: int, items: list<array<string, mixed>>, total: float} */
    public function getSummary(): array
    {
        $cart = $this->getOrCreateCartEntity(false);
        if ($cart === null) {
            return [
                'hasItems' => false,
                'itemCount' => 0,
                'items' => [],
                'total' => 0.0,
            ];
        }

        $activeItems = $cart->getActiveItems();
        if ($activeItems === []) {
            $legacy = $this->buildLegacyCartData($cart);
            if (!\is_array($legacy) || empty($legacy['product']) || !\is_array($legacy['product'])) {
                return [
                    'hasItems' => false,
                    'itemCount' => 0,
                    'items' => [],
                    'total' => 0.0,
                ];
            }

            return $this->buildSummaryFromLegacy($legacy);
        }

        $items = [];
        $itemCount = 0;
        $total = 0.0;
        foreach ($activeItems as $item) {
            $product = $item->getProductSnapshot();
            $gallery = $product['gallery'] ?? [];
            $image = null;
            if (\is_array($gallery) && isset($gallery[0]) && \is_array($gallery[0])) {
                $image = $gallery[0]['thumb'] ?? $gallery[0]['full'] ?? null;
            }

            $lineTotal = $item->getLineTotal();
            $items[] = [
                'cart_item_id' => $item->getId(),
                'product_id' => $item->getProductId(),
                'offer_id' => $item->getOfferId(),
                'supplier_id' => $item->getSupplierId(),
                'supplier_name' => $item->getSupplierName(),
                'name' => $item->getProductName(),
                'sku' => $item->getProductSku(),
                'quantity' => $item->getQuantity(),
                'price' => $item->getUnitPrice(),
                'line_total' => $lineTotal,
                'image' => \is_string($image) ? $image : null,
                'snapshot' => $product,
            ];
            $itemCount += $item->getQuantity();
            $total += $lineTotal;
        }

        return [
            'hasItems' => true,
            'itemCount' => $itemCount,
            'items' => $items,
            'total' => $total,
        ];
    }

    public function attachVisitorCartToCustomer(string $visitorId, User $customer): void
    {
        if (!VisitorIdResolver::isValid($visitorId)) {
            return;
        }

        $visitorCart = $this->cartRepository->findActiveByVisitorId($visitorId);
        if ($visitorCart === null) {
            return;
        }

        $customerCart = $this->cartRepository->findActiveByCustomer($customer);
        if ($customerCart === null) {
            $visitorCart->setCustomer($customer);
            $this->entityManager->flush();

            return;
        }

        if ($customerCart->getId() === $visitorCart->getId()) {
            return;
        }

        $this->mergeCartData($customerCart, $visitorCart);
        $this->entityManager->remove($visitorCart);
        $this->entityManager->flush();
    }

    public function transferCustomerCartToGuest(User $customer, string $newVisitorId): void
    {
        if (!VisitorIdResolver::isValid($newVisitorId)) {
            return;
        }

        $customerCart = $this->cartRepository->findActiveByCustomer($customer);
        if ($customerCart === null) {
            return;
        }

        $hasContents = $customerCart->getActiveItems() !== []
            || $customerCart->getContactData() !== null
            || $customerCart->getDeliveryData() !== null
            || $customerCart->getShipmentAddress() !== null;

        if ($hasContents) {
            $guestCart = (new Cart())
                ->setVisitorId($newVisitorId)
                ->setStatus(CartStatus::Active);

            $this->copyCartContents($guestCart, $customerCart);
            $this->entityManager->persist($guestCart);
        }

        $this->suspendCart($customerCart);
        $this->entityManager->flush();
    }

    public function restoreCustomerCartOnLogin(string $visitorId, User $customer): void
    {
        $suspendedCart = $this->cartRepository->findSuspendedByCustomer($customer);
        $visitorCart = VisitorIdResolver::isValid($visitorId)
            ? $this->cartRepository->findActiveByVisitorId($visitorId)
            : null;

        if ($suspendedCart !== null) {
            $this->reactivateCart($suspendedCart);

            if ($visitorCart !== null && $visitorCart->getId() !== $suspendedCart->getId()) {
                $this->mergeCartData($suspendedCart, $visitorCart);
                $this->entityManager->remove($visitorCart);
            }

            $this->entityManager->flush();

            return;
        }

        if (VisitorIdResolver::isValid($visitorId)) {
            $this->attachVisitorCartToCustomer($visitorId, $customer);
        }
    }

    /** @param array<string, mixed> $product */
    private function upsertCartItem(int $productId, int $quantity, array $product, bool $clearCheckout): void
    {
        $cart = $this->getOrCreateCartEntity(true);
        if ($cart === null) {
            return;
        }

        $product = CartLineKey::enrichProductSnapshot($product);
        $lineKey = CartLineKey::fromProduct($product);
        $existingItem = $this->findActiveItemByLineKey($cart, $lineKey);
        if ($existingItem !== null) {
            $this->syncCartItemFromProduct($existingItem, $productId, $quantity, $product);
        } else {
            $this->createCartItem($cart, $productId, $quantity, $product);
            if ($clearCheckout) {
                $this->clearCheckoutSessionData($cart);
            }
        }

        $cart->setCartData(null);
        $this->entityManager->flush();
    }

    /** @param array<string, mixed> $product */
    private function createCartItem(Cart $cart, int $productId, int $quantity, array $product): CartItem
    {
        $product = CartLineKey::enrichProductSnapshot($product);
        $meta = $this->extractOfferMeta($product);

        $item = (new CartItem())
            ->setProductId($productId)
            ->setQuantity($quantity)
            ->setProductName((string) ($product['name'] ?? ''))
            ->setProductSku($meta['sku'])
            ->setOfferId($meta['offer_id'])
            ->setSupplierId($meta['supplier_id'])
            ->setSupplierName($meta['supplier_name'])
            ->setUnitPrice((float) ($product['price'] ?? 0))
            ->setProductSnapshot($product)
            ->setStatus(CartItemStatus::Active);

        $cart->addItem($item);
        $this->entityManager->persist($item);

        return $item;
    }

    /** @param array<string, mixed> $product */
    private function syncCartItemFromProduct(CartItem $item, int $productId, int $quantity, array $product): void
    {
        $product = CartLineKey::enrichProductSnapshot($product);
        $meta = $this->extractOfferMeta($product);

        $item
            ->setProductId($productId)
            ->setQuantity($quantity)
            ->setProductName((string) ($product['name'] ?? ''))
            ->setProductSku($meta['sku'])
            ->setOfferId($meta['offer_id'])
            ->setSupplierId($meta['supplier_id'])
            ->setSupplierName($meta['supplier_name'])
            ->setUnitPrice((float) ($product['price'] ?? 0))
            ->setProductSnapshot($product);
    }

    /** @param array<string, mixed> $product */
    /** @return array{offer_id: ?int, supplier_id: ?int, supplier_name: ?string, sku: string} */
    private function extractOfferMeta(array $product): array
    {
        $supplier = \is_array($product['supplier'] ?? null) ? $product['supplier'] : [];
        $supplierId = (int) ($supplier['id'] ?? 0);
        $offerId = (int) ($product['selectedOfferId'] ?? 0);

        return [
            'offer_id' => $offerId > 0 ? $offerId : null,
            'supplier_id' => $supplierId > 0 ? $supplierId : null,
            'supplier_name' => trim((string) ($supplier['name'] ?? '')) ?: null,
            'sku' => trim((string) ($product['offerSku'] ?? $product['sku'] ?? '')),
        ];
    }

    private function deactivateCart(Cart $cart): void
    {
        $cart->setStatus(CartStatus::Inactive);
        foreach ($cart->getItems() as $item) {
            if ($item->isActive()) {
                $item->setStatus(CartItemStatus::Inactive);
            }
        }

        $cart->setCartData(null);
        $cart->setOrderData(null);
        $this->entityManager->flush();
    }

    private function suspendCart(Cart $cart): void
    {
        $cart->setStatus(CartStatus::Suspended);
        foreach ($cart->getItems() as $item) {
            if ($item->isActive()) {
                $item->setStatus(CartItemStatus::Inactive);
            }
        }

        $cart->setCartData(null);
        $cart->setOrderData(null);
    }

    private function reactivateCart(Cart $cart): void
    {
        $cart->setStatus(CartStatus::Active);

        foreach ($cart->getItems() as $item) {
            $item->setStatus(CartItemStatus::Active);
        }

        $cart->setCartData(null);
    }

    private function copyCartContents(Cart $target, Cart $source): void
    {
        foreach ($source->getActiveItems() as $sourceItem) {
            $this->createCartItem(
                $target,
                $sourceItem->getProductId(),
                $sourceItem->getQuantity(),
                $sourceItem->getProductSnapshot(),
            );
        }

        if ($source->getContactData() !== null) {
            $target->setContactData($source->getContactData());
        }

        if ($source->getShipmentAddress() !== null) {
            $this->shipmentAddressService->cloneAddressForCart($source->getShipmentAddress(), $target);
        } elseif ($source->getDeliveryData() !== null) {
            $target->setDeliveryData($source->getDeliveryData());
        }

        if (\is_array($source->getCartData())) {
            $target->setCartData($source->getCartData());
        }
    }

    private function deactivateActiveItems(Cart $cart, bool $clearCheckout): void
    {
        foreach ($cart->getItems() as $item) {
            if ($item->isActive()) {
                $item->setStatus(CartItemStatus::Inactive);
            }
        }

        if ($clearCheckout) {
            $this->clearCheckoutSessionData($cart);
        }
    }

    private function clearCheckoutSessionData(Cart $cart): void
    {
        $cart->setContactData(null);
        $this->shipmentAddressService->clearCartAddress($cart);
        $cart->setOrderData(null);
    }

    private function findActiveItemByLineKey(Cart $cart, string $lineKey): ?CartItem
    {
        foreach ($cart->getActiveItems() as $item) {
            if (CartLineKey::fromSnapshot($item->getProductSnapshot()) === $lineKey) {
                return $item;
            }
        }

        return null;
    }

    private function findActiveItemById(Cart $cart, int $cartItemId): ?CartItem
    {
        foreach ($cart->getActiveItems() as $item) {
            if ($item->getId() === $cartItemId) {
                return $item;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function buildLegacyCartData(Cart $cart): ?array
    {
        $activeItems = $cart->getActiveItems();
        if ($activeItems !== []) {
            $summary = $this->getSummary();
            if (!$summary['hasItems']) {
                return null;
            }

            $items = [];
            foreach ($summary['items'] as $summaryItem) {
                $items[] = [
                    'cart_item_id' => $summaryItem['cart_item_id'],
                    'product_id' => $summaryItem['product_id'],
                    'offer_id' => $summaryItem['offer_id'],
                    'quantity' => $summaryItem['quantity'],
                    'product' => $summaryItem['snapshot'],
                ];
            }

            $first = $items[0];

            return [
                'items' => $items,
                'itemCount' => $summary['itemCount'],
                'subtotal' => $summary['total'],
                'product_id' => $first['product_id'],
                'quantity' => $first['quantity'],
                'product' => $first['product'],
            ];
        }

        $legacy = $cart->getCartData();
        if (\is_array($legacy) && !empty($legacy['product']) && \is_array($legacy['product'])) {
            return $legacy;
        }

        return null;
    }

    /** @param array<string, mixed> $legacy */
    /** @return array{hasItems: bool, itemCount: int, items: list<array<string, mixed>>, total: float} */
    private function buildSummaryFromLegacy(array $legacy): array
    {
        $product = $legacy['product'];
        $quantity = max(1, (int) ($legacy['quantity'] ?? 1));
        $price = (float) ($product['price'] ?? 0);
        $gallery = $product['gallery'] ?? [];
        $image = null;
        if (\is_array($gallery) && isset($gallery[0]) && \is_array($gallery[0])) {
            $image = $gallery[0]['thumb'] ?? $gallery[0]['full'] ?? null;
        }

        $supplier = \is_array($product['supplier'] ?? null) ? $product['supplier'] : [];

        return [
            'hasItems' => true,
            'itemCount' => $quantity,
            'items' => [[
                'cart_item_id' => 0,
                'product_id' => (int) ($legacy['product_id'] ?? $product['id'] ?? 0),
                'offer_id' => (int) ($product['selectedOfferId'] ?? 0) ?: null,
                'supplier_id' => (int) ($supplier['id'] ?? 0) ?: null,
                'supplier_name' => (string) ($supplier['name'] ?? '') ?: null,
                'name' => (string) ($product['name'] ?? ''),
                'sku' => (string) ($product['offerSku'] ?? $product['sku'] ?? ''),
                'quantity' => $quantity,
                'price' => $price,
                'line_total' => $price * $quantity,
                'image' => \is_string($image) ? $image : null,
                'snapshot' => $product,
            ]],
            'total' => $price * $quantity,
        ];
    }

    /** @param array<string, mixed>|null $delivery */
    private function normalizeDeliveryData(?array $delivery): ?array
    {
        if (!\is_array($delivery) || ($delivery['deliveryMethod'] ?? '') === '') {
            return null;
        }

        $deliveryCost = isset($delivery['deliveryCost']) && is_numeric($delivery['deliveryCost'])
            ? round((float) $delivery['deliveryCost'], 2)
            : null;

        return [
            'deliveryMethod' => (string) $delivery['deliveryMethod'],
            'courierAddress' => $delivery['courierAddress'] ?? null,
            'npCityRef' => $delivery['npCityRef'] ?? null,
            'npCityName' => $delivery['npCityName'] ?? null,
            'npWarehouseRef' => $delivery['npWarehouseRef'] ?? null,
            'npWarehouseName' => $delivery['npWarehouseName'] ?? null,
            'deliveryCost' => $deliveryCost,
        ];
    }

    /** @param array<string, mixed>|null $delivery */
    /** @return array<string, mixed>|null */
    private function extractLegacyContactFromDelivery(?array $delivery): ?array
    {
        if (!\is_array($delivery) || ($delivery['customerName'] ?? '') === '') {
            return null;
        }

        return $this->normalizeContactData([
            'customerName' => (string) $delivery['customerName'],
            'customerPhone' => (string) ($delivery['customerPhone'] ?? ''),
            'customerEmail' => (string) ($delivery['customerEmail'] ?? ''),
        ]);
    }

    /** @param array<string, mixed> $contactData */
    /** @return array<string, mixed> */
    private function normalizeContactData(array $contactData): array
    {
        return [
            'customerName' => trim((string) ($contactData['customerName'] ?? '')),
            'customerPhone' => trim((string) ($contactData['customerPhone'] ?? '')),
            'customerEmail' => trim((string) ($contactData['customerEmail'] ?? '')),
            'doNotCall' => filter_var($contactData['doNotCall'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    private function getContactDataFromUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $contact = $user->toContactDataArray();
        if (($contact['customerName'] ?? '') === '') {
            return null;
        }

        return $contact;
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    private function mergeCartData(Cart $target, Cart $source): void
    {
        foreach ($source->getActiveItems() as $sourceItem) {
            $snapshot = $sourceItem->getProductSnapshot();
            $lineKey = CartLineKey::fromSnapshot($snapshot);
            $existing = $this->findActiveItemByLineKey($target, $lineKey);
            if ($existing !== null) {
                $stockQty = max(1, (int) ($snapshot['stockQty'] ?? 1));
                $existing->setQuantity(min($existing->getQuantity() + $sourceItem->getQuantity(), $stockQty));
                $this->syncCartItemFromProduct(
                    $existing,
                    $sourceItem->getProductId(),
                    $existing->getQuantity(),
                    $snapshot,
                );
                continue;
            }

            $item = (new CartItem())
                ->setProductId($sourceItem->getProductId())
                ->setQuantity($sourceItem->getQuantity())
                ->setProductName($sourceItem->getProductName())
                ->setProductSku($sourceItem->getProductSku())
                ->setOfferId($sourceItem->getOfferId())
                ->setSupplierId($sourceItem->getSupplierId())
                ->setSupplierName($sourceItem->getSupplierName())
                ->setUnitPrice($sourceItem->getUnitPrice())
                ->setProductSnapshot($snapshot)
                ->setStatus(CartItemStatus::Active);
            $target->addItem($item);
            $this->entityManager->persist($item);
        }

        if ($target->getContactData() === null && $source->getContactData() !== null) {
            $target->setContactData($source->getContactData());
        }

        if ($target->getShipmentAddress() === null && $source->getShipmentAddress() !== null) {
            $this->shipmentAddressService->cloneAddressForCart($source->getShipmentAddress(), $target);
        } elseif ($target->getDeliveryData() === null && $source->getDeliveryData() !== null) {
            $target->setDeliveryData($source->getDeliveryData());
        }

        if ($target->getOrderData() === null && $source->getOrderData() !== null) {
            $target->setOrderData($source->getOrderData());
        }

        if ($target->getActiveItems() === [] && \is_array($source->getCartData())) {
            $target->setCartData($source->getCartData());
        }
    }

    private function getOrCreateCartEntity(bool $createIfMissing): ?Cart
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $cart = $this->cartRepository->findActiveByCustomer($user);
            if ($cart !== null) {
                return $cart;
            }
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $visitorId = $this->visitorIdResolver->resolve($request);
        $cart = $this->cartRepository->findActiveByVisitorId($visitorId);
        if ($cart !== null) {
            if ($user instanceof User && $cart->getCustomer() === null) {
                $existingCustomerCart = $this->cartRepository->findActiveByCustomer($user);
                if ($existingCustomerCart !== null && $existingCustomerCart->getId() !== $cart->getId()) {
                    $this->mergeCartData($existingCustomerCart, $cart);
                    $this->entityManager->remove($cart);
                    $this->entityManager->flush();

                    return $existingCustomerCart;
                }

                $cart->setCustomer($user);
                $this->entityManager->flush();
            }

            return $cart;
        }

        if (!$createIfMissing) {
            return null;
        }

        $cart = (new Cart())
            ->setVisitorId($visitorId)
            ->setStatus(CartStatus::Active);

        if ($user instanceof User) {
            $cart->setCustomer($user);
        }

        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        return $cart;
    }
}
