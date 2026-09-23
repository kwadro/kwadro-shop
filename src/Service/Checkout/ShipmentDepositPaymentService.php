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

        $methods = $this->resolveAvailableGatewayMethods($site);
        if ($methods === []) {
            return ShipmentDepositCheckoutResult::unavailable($amount);
        }

        $gateways = [];
        foreach ($methods as $method) {
            $option = $this->resolveGatewayOption($order, $amount, $locale, $method);
            if ($option !== null) {
                $gateways[] = $option;
            }
        }

        if ($gateways === []) {
            return ShipmentDepositCheckoutResult::unavailable($amount);
        }

        return ShipmentDepositCheckoutResult::pending($amount, $gateways);
    }

    public function resolveQrPayloadForMethod(ShipmentDepositCheckoutResult $checkout, string $method): ?string
    {
        $gateway = $checkout->gateway($method);
        if ($gateway === null) {
            return null;
        }

        if ($method === ShopPaymentMethod::Monobank) {
            return $gateway->payUrl !== '' ? $gateway->payUrl : null;
        }

        if ($method === ShopPaymentMethod::Privatbank) {
            if ($gateway->liqpayWidget !== null) {
                return sprintf(
                    'https://www.liqpay.ua/api/3/checkout?data=%s&signature=%s',
                    rawurlencode($gateway->liqpayWidget['data']),
                    rawurlencode($gateway->liqpayWidget['signature']),
                );
            }

            return $gateway->payUrl !== '' ? $gateway->payUrl : null;
        }

        return null;
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

    private function resolveGatewayOption(
        Order $order,
        float $amount,
        string $locale,
        string $method,
    ): ?ShipmentDepositGatewayOption {
        $payment = $this->paymentRepository->findLatestPendingGatewayByOrderAndMethod($order, $method);
        if ($payment === null || abs($payment->getAmount() - $amount) > 0.009) {
            $payment = $this->orderCheckoutService->createShipmentDepositPayment($order, $amount, $method);
        }

        $payUrl = trim((string) ($payment->getRedirectUrl() ?? ''));
        if ($payUrl === '' || ($method === ShopPaymentMethod::Privatbank && !str_contains($payUrl, 'data='))) {
            try {
                if ($payUrl === '' || $payment->getGatewayResponse() === null) {
                    $result = $this->createGatewayInvoice($payment, $amount, $order, $locale);
                    $payUrl = $result->getType() === 'redirect'
                        ? (string) $result->getUrl()
                        : $this->buildLiqPayCheckoutUrl(
                            (string) $result->getData(),
                            (string) $result->getSignature(),
                        );
                } else {
                    $payUrl = $this->buildLiqPayPayUrlFromPayment($payment) ?? $payUrl;
                }
            } catch (\Throwable $exception) {
                $this->logger->error('Shipment deposit invoice creation failed.', [
                    'order_id' => $order->getId(),
                    'payment_id' => $payment->getId(),
                    'method' => $method,
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }
        }

        if ($method === ShopPaymentMethod::Privatbank) {
            $payUrl = $this->buildLiqPayPayUrlFromPayment($payment) ?? $payUrl;
            if ($payUrl !== '' && !str_contains($payUrl, 'data=')) {
                return null;
            }
            if ($payUrl !== trim((string) ($payment->getRedirectUrl() ?? ''))) {
                $this->orderCheckoutService->updatePaymentGatewayData(
                    $payment,
                    $payUrl,
                    $payment->getGatewayResponse(),
                );
            }
        }

        if ($payUrl === '') {
            return null;
        }

        return new ShipmentDepositGatewayOption(
            method: $method,
            payment: $payment,
            payUrl: $payUrl,
            liqpayWidget: $this->extractLiqPayWidget($payment, $method),
        );
    }

    private function buildLiqPayPayUrlFromPayment(Payment $payment): ?string
    {
        $widget = $this->extractLiqPayWidget($payment, ShopPaymentMethod::Privatbank);
        if ($widget === null) {
            return null;
        }

        return $this->buildLiqPayCheckoutUrl($widget['data'], $widget['signature']);
    }

    private function buildLiqPayCheckoutUrl(string $data, string $signature): string
    {
        return sprintf(
            'https://www.liqpay.ua/api/3/checkout?data=%s&signature=%s',
            rawurlencode($data),
            rawurlencode($signature),
        );
    }

    /** @return array{data: string, signature: string}|null */
    private function extractLiqPayWidget(Payment $payment, string $method): ?array
    {
        if ($method !== ShopPaymentMethod::Privatbank) {
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

    private function findPendingMonobankDepositPayment(Order $order): ?Payment
    {
        $payment = $this->paymentRepository->findLatestPendingGatewayByOrderAndMethod(
            $order,
            ShopPaymentMethod::Monobank,
        );
        if ($payment !== null) {
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

    /** @return list<string> */
    private function resolveAvailableGatewayMethods(Site $site): array
    {
        $enabled = $site->getActivePaymentMethods();
        $methods = [];

        $monoAllowed = $enabled === [] || in_array(ShopPaymentMethod::Monobank, $enabled, true);
        $privatAllowed = $enabled === [] || in_array(ShopPaymentMethod::Privatbank, $enabled, true);

        if ($monoAllowed && $this->paymentCheckoutService->isMonobankConfigured()) {
            $methods[] = ShopPaymentMethod::Monobank;
        }
        if ($privatAllowed && $this->paymentCheckoutService->isPrivatBankConfigured()) {
            $methods[] = ShopPaymentMethod::Privatbank;
        }

        return $methods;
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
