<?php

namespace App\Service\Checkout\LiqPay;

use App\Entity\Payment;
use App\Entity\PaymentStatus;
use App\Entity\ShopPaymentMethod;
use App\Repository\PaymentRepository;
use App\Service\Checkout\OrderCheckoutService;
use Psr\Log\LoggerInterface;

final class LiqPayCallbackHandler
{
    public function __construct(
        private readonly LiqPayClient $liqPayClient,
        private readonly PaymentRepository $paymentRepository,
        private readonly OrderCheckoutService $orderCheckoutService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return 'invalid_signature'|'invalid_payload'|'processed'|'ignored'
     */
    public function handleEncodedCallback(string $encodedData, string $signature): string
    {
        if ($encodedData === '') {
            return 'invalid_payload';
        }

        if (!$this->liqPayClient->isValidSignature($encodedData, $signature)) {
            $this->logger->warning('LiqPay callback rejected: invalid signature');

            return 'invalid_signature';
        }

        $payload = $this->liqPayClient->decodeData($encodedData);
        if ($payload === null) {
            return 'invalid_payload';
        }

        return $this->applyPayload($payload);
    }

    /**
     * Syncs a pending PrivatBank/LiqPay payment with the gateway (browser return / missed callback).
     */
    public function syncPendingPayment(Payment $payment): bool
    {
        if ($payment->getMethod() !== ShopPaymentMethod::Privatbank) {
            return false;
        }

        if ($payment->getStatus() === PaymentStatus::Success) {
            return true;
        }

        if ($payment->getStatus() !== PaymentStatus::Pending) {
            return false;
        }

        $orderId = (string) ($payment->getGatewayReference() ?? '');
        $payload = $this->liqPayClient->fetchStatus($orderId);
        if ($payload === null) {
            $this->logger->warning('LiqPay status sync failed', [
                'payment_id' => $payment->getId(),
                'order_id' => $orderId,
            ]);

            return false;
        }

        $this->applyPayload($payload, $payment);

        return $payment->getStatus() === PaymentStatus::Success;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return 'processed'|'ignored'
     */
    private function applyPayload(array $payload, ?Payment $payment = null): string
    {
        $payment ??= $this->resolvePayment($payload);
        if ($payment === null) {
            $this->logger->warning('LiqPay callback: payment not found', [
                'order_id' => $payload['order_id'] ?? null,
            ]);

            return 'processed';
        }

        if ($payment->getMethod() !== ShopPaymentMethod::Privatbank) {
            return 'ignored';
        }

        $status = (string) ($payload['status'] ?? '');
        if (LiqPayClient::isSuccessStatus($status)) {
            if ($payment->getStatus() !== PaymentStatus::Success) {
                $this->orderCheckoutService->markPaymentSuccessful($payment, $payload);
            }

            return 'processed';
        }

        if (LiqPayClient::isFailureStatus($status)) {
            if ($payment->getStatus() === PaymentStatus::Pending) {
                $this->orderCheckoutService->markPaymentFailed($payment, $payload);
            }

            return 'processed';
        }

        $this->logger->info('LiqPay intermediate status ignored for completion', [
            'payment_id' => $payment->getId(),
            'status' => $status,
        ]);

        return 'ignored';
    }

    /** @param array<string, mixed> $payload */
    private function resolvePayment(array $payload): ?Payment
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        if ($orderId === '') {
            return null;
        }

        return $this->paymentRepository->findOneByGatewayReference($orderId);
    }
}
