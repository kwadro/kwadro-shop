<?php

namespace App\Service\Checkout;

use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Entity\Payment;
use App\Entity\PaymentStatus;
use App\Entity\ShopPaymentMethod;
use App\Entity\Site;
use App\Repository\PaymentRepository;
use App\Service\Checkout\Monobank\MonobankInvoiceStatusClient;
use Psr\Log\LoggerInterface;

final class ShipmentDepositPaymentService
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly OrderCheckoutService $orderCheckoutService,
        private readonly PaymentCheckoutService $paymentCheckoutService,
        private readonly ShipmentPayTokenService $tokenService,
        private readonly MonobankInvoiceStatusClient $monobankInvoiceStatusClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function resolveCheckout(Order $order, Site $site, string $locale): ShipmentDepositCheckoutResult
    {
        $amount = max(0.0, round($site->getCodPrepaymentAmount(), 2));
        if ($amount <= 0) {
            return ShipmentDepositCheckoutResult::unavailable(0.0);
        }

        $this->syncPendingDepositFromGateway($order);

        if ($order->getStatus() === OrderStatus::DepositPaid || $this->isDepositPaid($order, $amount)) {
            return ShipmentDepositCheckoutResult::paid($amount);
        }

        if ($order->getStatus() !== OrderStatus::AwaitingDepositForShipment) {
            return ShipmentDepositCheckoutResult::unavailable($amount);
        }

        $gatewayMethod = $this->resolveGatewayMethod($site);
        if ($gatewayMethod === null) {
            return ShipmentDepositCheckoutResult::unavailable($amount);
        }

        $payment = $this->paymentRepository->findLatestPendingGatewayByOrder($order);
        if ($payment === null || abs($payment->getAmount() - $amount) > 0.009) {
            $payment = $this->orderCheckoutService->createShipmentDepositPayment($order, $amount, $gatewayMethod);
        }

        $payUrl = trim((string) ($payment->getRedirectUrl() ?? ''));
        if ($payUrl === '') {
            try {
                $result = $this->createGatewayInvoice($payment, $amount, $order, $locale);
                $payUrl = $result->getType() === 'redirect'
                    ? (string) $result->getUrl()
                    : 'https://www.liqpay.ua/api/3/checkout';
            } catch (\Throwable $exception) {
                $this->logger->error('Shipment deposit invoice creation failed.', [
                    'order_id' => $order->getId(),
                    'payment_id' => $payment->getId(),
                    'error' => $exception->getMessage(),
                ]);

                return ShipmentDepositCheckoutResult::unavailable($amount);
            }
        }

        if ($payUrl === '') {
            return ShipmentDepositCheckoutResult::unavailable($amount);
        }

        return ShipmentDepositCheckoutResult::pending($amount, $payment, $payUrl, $gatewayMethod);
    }

    public function resolveQrPayload(ShipmentDepositCheckoutResult $checkout): ?string
    {
        if ($checkout->state !== ShipmentDepositCheckoutResult::STATE_PENDING || $checkout->payUrl === null) {
            return null;
        }

        if ($checkout->gatewayMethod === ShopPaymentMethod::Monobank) {
            return $checkout->payUrl;
        }

        $payment = $checkout->payment;
        if ($payment === null) {
            return $checkout->payUrl;
        }

        $gatewayResponse = $payment->getGatewayResponse();
        if (\is_array($gatewayResponse) && ($gatewayResponse['type'] ?? '') === 'liqpay_form') {
            return sprintf(
                'https://www.liqpay.ua/api/3/checkout?data=%s&signature=%s',
                rawurlencode((string) ($gatewayResponse['data'] ?? '')),
                rawurlencode((string) ($gatewayResponse['signature'] ?? '')),
            );
        }

        return $checkout->payUrl;
    }

    /** @return array{data: string, signature: string}|null */
    public function resolveLiqPayWidget(ShipmentDepositCheckoutResult $checkout): ?array
    {
        if ($checkout->state !== ShipmentDepositCheckoutResult::STATE_PENDING) {
            return null;
        }

        if ($checkout->gatewayMethod !== ShopPaymentMethod::Privatbank) {
            return null;
        }

        $payment = $checkout->payment;
        if ($payment === null) {
            return null;
        }

        $gatewayResponse = $payment->getGatewayResponse();
        if (!\is_array($gatewayResponse) || ($gatewayResponse['type'] ?? '') !== 'liqpay_form') {
            return null;
        }

        $data = trim((string) ($gatewayResponse['data'] ?? ''));
        $signature = trim((string) ($gatewayResponse['signature'] ?? ''));

        if ($data === '' || $signature === '') {
            return null;
        }

        return [
            'data' => $data,
            'signature' => $signature,
        ];
    }

    private function isDepositPaid(Order $order, float $amount): bool
    {
        if ($order->getStatus() === OrderStatus::DepositPaid) {
            return true;
        }

        if ($order->getPayAmount() + 0.009 >= $amount) {
            return true;
        }

        foreach ($order->getPayments() as $payment) {
            if ($payment->getMethod() === ShopPaymentMethod::OnDelivery) {
                continue;
            }

            if ($payment->getStatus() === PaymentStatus::Success && $payment->getAmount() + 0.009 >= $amount) {
                return true;
            }
        }

        return false;
    }

    /**
     * Syncs pending Monobank deposit payment with gateway status (browser return / missed webhook).
     */
    public function syncPendingDepositFromGateway(Order $order): bool
    {
        if ($order->getStatus() === OrderStatus::DepositPaid) {
            return true;
        }

        $payment = $this->findPendingMonobankDepositPayment($order);
        if ($payment === null) {
            return $this->isDepositPaid($order, max(0.0, round($order->getSite()?->getCodPrepaymentAmount() ?? 0.0, 2)));
        }

        $invoiceId = $this->resolveMonobankInvoiceId($payment);
        if ($invoiceId === null) {
            return false;
        }

        $payload = $this->monobankInvoiceStatusClient->fetchStatus($invoiceId);
        if ($payload === null) {
            $this->logger->warning('Monobank invoice status sync failed.', [
                'order_id' => $order->getId(),
                'payment_id' => $payment->getId(),
                'invoiceId' => $invoiceId,
            ]);

            return false;
        }

        $status = (string) ($payload['status'] ?? '');
        if ($status === 'success') {
            if ($payment->getMonobankInvoiceId() === null) {
                $payment->setMonobankInvoiceId($invoiceId);
            }
            $this->orderCheckoutService->markPaymentSuccessful($payment, $payload);

            return true;
        }

        if (in_array($status, ['failure', 'expired', 'reversed'], true)) {
            $this->orderCheckoutService->markPaymentFailed($payment, $payload);
        }

        return false;
    }

    private function findPendingMonobankDepositPayment(Order $order): ?Payment
    {
        $payment = $this->paymentRepository->findLatestPendingGatewayByOrder($order);
        if ($payment !== null && $payment->getMethod() === ShopPaymentMethod::Monobank) {
            return $payment;
        }

        foreach ($order->getPayments() as $candidate) {
            if ($candidate->getMethod() !== ShopPaymentMethod::Monobank) {
                continue;
            }
            if ($candidate->getStatus() !== PaymentStatus::Pending) {
                continue;
            }
            if (!str_contains((string) $candidate->getGatewayReference(), '-D')) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    private function resolveMonobankInvoiceId(Payment $payment): ?string
    {
        $invoiceId = trim((string) ($payment->getMonobankInvoiceId() ?? ''));
        if ($invoiceId !== '') {
            return $invoiceId;
        }

        $gatewayResponse = $payment->getGatewayResponse();
        if (\is_array($gatewayResponse)) {
            $fromResponse = trim((string) ($gatewayResponse['invoiceId'] ?? ''));
            if ($fromResponse !== '') {
                return $fromResponse;
            }
        }

        return null;
    }

    private function resolveGatewayMethod(Site $site): ?string
    {
        $enabled = $site->getActivePaymentMethods();
        if (in_array(ShopPaymentMethod::Monobank, $enabled, true) && $this->paymentCheckoutService->isMonobankConfigured()) {
            return ShopPaymentMethod::Monobank;
        }

        if (in_array(ShopPaymentMethod::Privatbank, $enabled, true) && $this->paymentCheckoutService->isPrivatBankConfigured()) {
            return ShopPaymentMethod::Privatbank;
        }

        if ($this->paymentCheckoutService->isMonobankConfigured()) {
            return ShopPaymentMethod::Monobank;
        }

        if ($this->paymentCheckoutService->isPrivatBankConfigured()) {
            return ShopPaymentMethod::Privatbank;
        }

        return null;
    }

    private function createGatewayInvoice(Payment $payment, float $amount, Order $order, string $locale): PaymentRedirectResult
    {
        $description = sprintf('Завдаток за відправку замовлення %s', $order->getOrderNumber());
        $redirectParams = [
            '_locale' => $locale,
            'orderNumber' => $order->getOrderNumber(),
            'token' => $this->tokenService->generateForOrder($order),
            'return' => '1',
        ];

        return match ($payment->getMethod()) {
            ShopPaymentMethod::Monobank => $this->paymentCheckoutService->createMonobankInvoice(
                $payment,
                $amount,
                $description,
                $locale,
                'shop_order_shipment_pay',
                $redirectParams,
            ),
            ShopPaymentMethod::Privatbank => $this->paymentCheckoutService->createPrivatBankInvoice(
                $payment,
                $amount,
                $description,
                $locale,
                'shop_order_shipment_pay',
                $redirectParams,
            ),
            default => throw new \InvalidArgumentException(sprintf('Unsupported deposit gateway "%s".', $payment->getMethod())),
        };
    }
}
