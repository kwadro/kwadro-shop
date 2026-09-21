<?php

namespace App\Service\Checkout;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderItemStatus;
use App\Entity\OrderEmailEvent;
use App\Entity\OrderStatus;
use App\Entity\Payment;
use App\Entity\PaymentStatus;
use App\Entity\ShopPaymentMethod;
use App\Entity\Site;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\PaymentRepository;
use App\Repository\SiteRepository;
use App\Service\Cart\CartStorageService;
use App\Service\Mail\OrderEmailMailer;
use App\Service\NovaPoshta\NovaPoshtaWaybillService;
use App\Service\Shipment\ShipmentAddressService;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

class OrderCheckoutService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly CartStorageService $cartStorage,
        private readonly ShipmentAddressService $shipmentAddressService,
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly OrderEmailMailer $orderEmailMailer,
        private readonly NovaPoshtaWaybillService $novaPoshtaWaybillService,
        private readonly SiteRepository $siteRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $cart
     * @param array<string, mixed> $checkoutData
     */
    public function resolveOrCreateOrderForCheckout(
        array $cart,
        array $checkoutData,
        ?User $customer,
        ?string $visitorId,
        string $locale,
        ?string $domain = null,
    ): Order {
        $site = $this->resolveSiteForDomain($domain);
        $existing = $this->resolveOrderFromCartSession();
        if ($existing !== null && !in_array($existing->getStatus(), [OrderStatus::Paid, OrderStatus::DepositPaid], true)) {
            $this->syncOrderTotalsFromCheckout($existing, $cart, $checkoutData);
            if ($existing->getSite() === null && $site !== null) {
                $existing->setSite($site);
                $this->entityManager->flush();
            }

            return $existing;
        }

        return $this->createOrder($cart, $checkoutData, $customer, $visitorId, $locale, $site);
    }

    /**
     * @param array<string, mixed> $cart
     * @param array<string, mixed> $checkoutData
     */
    public function syncOrderTotalsFromCheckout(Order $order, array $cart, array $checkoutData): void
    {
        $order
            ->setAmount(round($this->resolveCartSubtotal($cart), 2))
            ->setShippingCost($this->extractShippingCost($checkoutData));

        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $cart
     * @param array<string, mixed> $checkoutData
     * @throws Throwable
     */
    public function createOrder(
        array $cart,
        array $checkoutData,
        ?User $customer,
        ?string $visitorId,
        string $locale,
        ?Site $site = null,
    ): Order {
        $subtotal = $this->resolveCartSubtotal($cart);
        $shippingCost = $this->extractShippingCost($checkoutData);
        $amount = round($subtotal, 2);

        $activeCart = $this->cartStorage->getActiveCart();
        $contactData = $this->resolveContactData($checkoutData, $activeCart);
        $deliveryData = $this->extractDeliveryData($checkoutData);

        $order = (new Order())
            ->setOrderNumber($this->orderNumberGenerator->generateNext())
            ->setStatus(OrderStatus::Created)
            ->setCustomer($customer)
            ->setSite($site)
            ->setVisitorId($visitorId !== '' ? $visitorId : null)
            ->setCartData($cart)
            ->applyContactData($contactData)
            ->setDeliveryData($deliveryData)
            ->setShippingCost($shippingCost)
            ->setAmount($amount)
            ->setLocale($locale);

        $this->addOrderItemsFromCartData($order, $cart);
        if ($activeCart !== null) {
            $attachedAddress = $this->shipmentAddressService->attachCartAddressToOrder($activeCart, $order);
            if ($attachedAddress === null && ($deliveryData['deliveryMethod'] ?? '') !== '') {
                $this->shipmentAddressService->attachDeliveryDataToOrder($order, $deliveryData);
            }
        } elseif (($deliveryData['deliveryMethod'] ?? '') !== '') {
            $this->shipmentAddressService->attachDeliveryDataToOrder($order, $deliveryData);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->persistSavedAddressForCustomer($order, $customer);
        $this->persistCustomerContact($customer ?? $order->getCustomer(), $contactData);

        return $order;
    }

    public function createPendingPayment(
        Order $order,
        string $method,
        ?float $paymentAmount = null,
        ?Site $site = null,
    ): Payment {
        if ($method === ShopPaymentMethod::OnDelivery) {
            throw new \InvalidArgumentException('Use completeOnDeliveryCheckout() for on_delivery orders.');
        }

        $this->failPendingPayments($order);
        $order->setStatus(OrderStatus::InProcess);

        $payAmount = $paymentAmount ?? $order->getAmount();

        $payment = (new Payment())
            ->setOrder($order)
            ->setMethod($method)
            ->setStatus(PaymentStatus::Pending)
            ->setAmount($payAmount)
            ->setCurrency($order->getCurrency())
            ->setGatewayReference($order->getOrderNumber());

        $order->addPayment($payment);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $payment->setGatewayReference(sprintf('%s-P%d', $order->getOrderNumber(), $payment->getId()));
        $this->entityManager->flush();

        $this->syncCartOrderReference($order, $payment);

        return $payment;
    }

    public function completeOnDeliveryCheckout(Order $order, ?Site $site = null): void
    {
        $this->failPendingPayments($order);

        $site ??= $order->getSite();
        $prepaymentAmount = max(0.0, round($site?->getCodPrepaymentAmount() ?? 0.0, 2));

        if ($prepaymentAmount > 0) {
            $order->setStatus(OrderStatus::AwaitingDepositForShipment);
            $this->entityManager->flush();
            $this->syncCartOrderReference($order, null, ShopPaymentMethod::OnDelivery);
            $this->orderEmailMailer->sendForOrder(
                $order,
                OrderEmailEvent::WaitPaymentForShipment,
                null,
            );

            return;
        }

        $order->setStatus(OrderStatus::InProcess);
        $this->confirmOrderItems($order);
        $this->entityManager->flush();
        $this->syncCartOrderReference($order, null, ShopPaymentMethod::OnDelivery);
        $this->novaPoshtaWaybillService->createForPaidOrder($order, ShopPaymentMethod::OnDelivery);
        $this->orderEmailMailer->sendForOrder($order, OrderEmailEvent::OrderCreated, null);
        $this->cartStorage->deactivateCartForOrder($order);
    }

    public function createShipmentDepositPayment(Order $order, float $amount, string $gatewayMethod): Payment
    {
        foreach ($order->getPayments() as $existingPayment) {
            if ($existingPayment->getStatus() !== PaymentStatus::Pending) {
                continue;
            }

            if ($existingPayment->getMethod() === ShopPaymentMethod::OnDelivery) {
                continue;
            }

            $existingPayment->setStatus(PaymentStatus::Failed);
            $existingPayment->setResultData(['source' => 'superseded_by_new_deposit_attempt']);
        }

        $payment = (new Payment())
            ->setOrder($order)
            ->setMethod($gatewayMethod)
            ->setStatus(PaymentStatus::Pending)
            ->setAmount(round(max(0, $amount), 2))
            ->setCurrency($order->getCurrency())
            ->setGatewayReference($order->getOrderNumber());

        $order->addPayment($payment);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $payment->setGatewayReference(sprintf('%s-D%d', $order->getOrderNumber(), $payment->getId()));
        $this->entityManager->flush();

        return $payment;
    }

    public function updatePaymentGatewayData(Payment $payment, ?string $redirectUrl, ?array $gatewayResponse): void
    {
        $payment->setRedirectUrl($redirectUrl);
        if ($gatewayResponse !== null) {
            $payment->setGatewayResponse($gatewayResponse);
        }

        $this->entityManager->flush();
        $this->syncCartOrderReference($payment->getOrder(), $payment);
    }

    public function markPaymentSuccessful(Payment $payment, ?array $resultData = null): void
    {
        $wasPending = $payment->getStatus() === PaymentStatus::Pending;

        $payment->setStatus(PaymentStatus::Success);
        $payment->setResultData($resultData);

        $order = $payment->getOrder();
        $isShipmentDeposit = $order !== null
            && $payment->getMethod() !== ShopPaymentMethod::OnDelivery
            && (in_array($order->getStatus(), [OrderStatus::AwaitingDepositForShipment, OrderStatus::DepositPaid], true)
                || str_contains($payment->getGatewayReference(), '-D'));

        if ($order !== null) {
            $order->setStatus($isShipmentDeposit ? OrderStatus::DepositPaid : OrderStatus::Paid);
            $this->confirmOrderItems($order);
        }

        $this->entityManager->flush();

        if ($order !== null) {
            if ($wasPending && $payment->getMethod() !== ShopPaymentMethod::OnDelivery && !$isShipmentDeposit) {
                $this->orderEmailMailer->sendForOrder($order, OrderEmailEvent::OrderCreated, $payment);
                $this->orderEmailMailer->sendForOrder($order, OrderEmailEvent::OrderPaid, $payment);
            }

            if ($wasPending && ($payment->getMethod() === ShopPaymentMethod::Monobank || $isShipmentDeposit)) {
                $this->novaPoshtaWaybillService->createForPaidOrder(
                    $order,
                    $isShipmentDeposit ? ShopPaymentMethod::OnDelivery : $payment->getMethod(),
                );
            }

            if ($wasPending && $isShipmentDeposit) {
                $this->orderEmailMailer->sendForOrder($order, OrderEmailEvent::DepositPay, $payment);
            }

            $this->cartStorage->deactivateCartForOrder($order);
        }
    }

    public function markPaymentFailed(Payment $payment, ?array $resultData = null): void
    {
        $payment->setStatus(PaymentStatus::Failed);
        $payment->setResultData($resultData);
        $this->entityManager->flush();
    }

    public function resolveOrderFromCartSession(): ?Order
    {
        $orderData = $this->cartStorage->getOrderData();
        if (!\is_array($orderData)) {
            return null;
        }

        $orderId = (int) ($orderData['order_id'] ?? 0);
        if ($orderId > 0) {
            $order = $this->orderRepository->find($orderId);
            if ($order instanceof Order) {
                return $order;
            }
        }

        $orderNumber = (string) ($orderData['order_number'] ?? $orderData['id'] ?? '');
        if ($orderNumber !== '') {
            return $this->orderRepository->findOneByOrderNumber($orderNumber);
        }

        return null;
    }

    public function completeOrderFromCartSession(): ?Order
    {
        $order = $this->resolveOrderFromCartSession();
        if ($order === null) {
            return null;
        }

        $orderData = $this->cartStorage->getOrderData();
        $sessionPaymentMethod = \is_array($orderData)
            ? (string) ($orderData['payment_method'] ?? '')
            : '';

        if (
            $sessionPaymentMethod === ShopPaymentMethod::OnDelivery
            && $order->getStatus() === OrderStatus::AwaitingDepositForShipment
        ) {
            $this->confirmOrderItems($order);
            $this->entityManager->flush();
            $this->cartStorage->deactivateCartForOrder($order);

            return $order;
        }

        if (
            $sessionPaymentMethod === ShopPaymentMethod::OnDelivery
            && $order->getStatus() === OrderStatus::InProcess
        ) {
            $this->cartStorage->deactivateCartForOrder($order);

            return $order;
        }

        $payment = $this->paymentRepository->findLatestPendingByOrder($order);
        if ($payment !== null && $payment->getMethod() === ShopPaymentMethod::OnDelivery) {
            $this->confirmOrderItems($order);
            $this->entityManager->flush();
            $this->cartStorage->deactivateCartForOrder($order);

            return $order;
        }

        if ($payment !== null) {
            $this->markPaymentSuccessful($payment, ['source' => 'success_return']);
        } elseif ($order->getStatus() === OrderStatus::InProcess) {
            $order->setStatus(OrderStatus::Paid);
            $this->confirmOrderItems($order);
            $this->entityManager->flush();
            $this->syncCartOrderReference($order, null);
            $this->cartStorage->deactivateCartForOrder($order);
        } elseif ($order->getStatus() === OrderStatus::Paid) {
            $this->cartStorage->deactivateCartForOrder($order);
        }

        return $order;
    }

    public function resolveRecentPaidOrder(?User $customer, ?string $visitorId): ?Order
    {
        return $this->orderRepository->findLatestPaidForSession($customer, $visitorId);
    }

    /** @param array<string, mixed> $cart */
    private function addOrderItemsFromCartData(Order $order, array $cart): void
    {
        $items = $cart['items'] ?? null;
        if (\is_array($items) && $items !== []) {
            foreach ($items as $cartItem) {
                if (!\is_array($cartItem)) {
                    continue;
                }

                $this->persistOrderItemFromCartLine($order, $cartItem);
            }

            return;
        }

        $product = $cart['product'] ?? null;
        if (!\is_array($product)) {
            return;
        }

        $this->persistOrderItemFromCartLine($order, [
            'product_id' => (int) ($cart['product_id'] ?? $product['id'] ?? 0),
            'quantity' => max(1, (int) ($cart['quantity'] ?? 1)),
            'product' => $product,
            'offer_id' => $product['selectedOfferId'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $cartItem */
    private function persistOrderItemFromCartLine(Order $order, array $cartItem): void
    {
        $product = $cartItem['product'] ?? null;
        if (!\is_array($product)) {
            return;
        }

        $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));
        $unitPrice = (float) ($product['price'] ?? 0);
        $supplier = \is_array($product['supplier'] ?? null) ? $product['supplier'] : [];
        $offerId = (int) ($cartItem['offer_id'] ?? $product['selectedOfferId'] ?? 0);
        $supplierId = (int) ($supplier['id'] ?? 0);

        $item = (new OrderItem())
            ->setProductId((int) ($cartItem['product_id'] ?? $product['id'] ?? 0))
            ->setQuantity($quantity)
            ->setProductName((string) ($product['name'] ?? ''))
            ->setProductSku((string) ($product['offerSku'] ?? $product['sku'] ?? ''))
            ->setOfferId($offerId > 0 ? $offerId : null)
            ->setSupplierId($supplierId > 0 ? $supplierId : null)
            ->setSupplierName(trim((string) ($supplier['name'] ?? '')) ?: null)
            ->setUnitPrice($unitPrice)
            ->setLineTotal($unitPrice * $quantity)
            ->setProductSnapshot($product)
            ->setStatus(OrderItemStatus::Pending);

        $order->addItem($item);
        $this->entityManager->persist($item);
    }

    /** @param array<string, mixed> $cart */
    public function resolveCartSubtotal(array $cart): float
    {
        if (isset($cart['subtotal']) && is_numeric($cart['subtotal'])) {
            return round((float) $cart['subtotal'], 2);
        }

        $items = $cart['items'] ?? [];
        if (\is_array($items) && $items !== []) {
            $total = 0.0;
            foreach ($items as $cartItem) {
                if (!\is_array($cartItem)) {
                    continue;
                }

                $product = $cartItem['product'] ?? null;
                if (!\is_array($product)) {
                    continue;
                }

                $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));
                $total += (float) ($product['price'] ?? 0) * $quantity;
            }

            return round($total, 2);
        }

        return round((float) ($cart['product']['price'] ?? 0) * (int) ($cart['quantity'] ?? 1), 2);
    }

    private function confirmOrderItems(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            if ($item->getStatus() === OrderItemStatus::Pending) {
                $item->setStatus(OrderItemStatus::Confirmed);
            }
        }
    }

    private function failPendingPayments(Order $order): void
    {
        foreach ($order->getPayments() as $payment) {
            if ($payment->getStatus() !== PaymentStatus::Pending) {
                continue;
            }

            $payment->setStatus(PaymentStatus::Failed);
            $payment->setResultData(['source' => 'superseded_by_new_attempt']);
        }
    }

    private function syncCartOrderReference(Order $order, ?Payment $payment, ?string $paymentMethod = null): void
    {
        $payload = [
            'order_id' => $order->getId(),
            'order_number' => $order->getOrderNumber(),
            'id' => $order->getOrderNumber(),
            'amount' => $order->getAmount(),
            'status' => $order->getStatus()->value,
            'payment_method' => $paymentMethod ?? $payment?->getMethod(),
            'payment_id' => $payment?->getId(),
            'payment_status' => $payment?->getStatus()?->value,
            'created_at' => $order->getCreatedAt()?->format(DATE_ATOM),
        ];

        $this->cartStorage->setOrderData($payload);
    }

    /** @param array<string, mixed> $checkoutData */
    /** @return array<string, string> */
    private function resolveContactData(array $checkoutData, ?\App\Entity\Cart $activeCart): array
    {
        $cartContact = $activeCart?->getContactData();
        if (\is_array($cartContact) && ($cartContact['customerName'] ?? '') !== '') {
            return $this->extractContactData($cartContact);
        }

        return $this->extractContactData($checkoutData);
    }

    /** @param array<string, mixed> $checkoutData */
    /** @return array<string, mixed> */
    private function extractContactData(array $checkoutData): array
    {
        return [
            'customerName' => trim((string) ($checkoutData['customerName'] ?? '')),
            'customerPhone' => trim((string) ($checkoutData['customerPhone'] ?? '')),
            'customerEmail' => trim((string) ($checkoutData['customerEmail'] ?? '')),
            'doNotCall' => filter_var($checkoutData['doNotCall'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /** @param array<string, mixed> $checkoutData */
    /** @return array<string, mixed> */
    private function extractDeliveryData(array $checkoutData): array
    {
        return [
            'deliveryMethod' => (string) ($checkoutData['deliveryMethod'] ?? ''),
            'courierAddress' => $checkoutData['courierAddress'] ?? null,
            'npCityRef' => $checkoutData['npCityRef'] ?? null,
            'npCityName' => $checkoutData['npCityName'] ?? null,
            'npWarehouseRef' => $checkoutData['npWarehouseRef'] ?? null,
            'npWarehouseName' => $checkoutData['npWarehouseName'] ?? null,
            'deliveryCost' => $this->extractShippingCost($checkoutData),
        ];
    }

    /** @param array<string, mixed> $checkoutData */
    private function extractShippingCost(array $checkoutData): float
    {
        if (!in_array($checkoutData['deliveryMethod'] ?? '', ['np_branch', 'np_postomat'], true)) {
            return 0.0;
        }

        return max(0.0, round((float) ($checkoutData['deliveryCost'] ?? 0), 2));
    }

    private function persistSavedAddressForCustomer(Order $order, ?User $customer): void
    {
        $this->shipmentAddressService->saveOrderAddressToUserAccount($order, $customer);
        $this->entityManager->flush();
    }

    /** @param array<string, string> $contactData */
    private function persistCustomerContact(?User $customer, array $contactData): void
    {
        if ($customer === null || ($contactData['customerName'] ?? '') === '') {
            return;
        }

        $customer->applyContactData($contactData);
        $this->entityManager->flush();
    }

    private function resolveSiteForDomain(?string $domain): ?Site
    {
        $domain = trim((string) $domain);
        if ($domain === '') {
            return null;
        }

        return $this->siteRepository->findOneBy(['domain' => $domain]);
    }
}
