<?php

namespace App\Service\Checkout\Monobank;

use App\Entity\Payment;
use App\Entity\PaymentStatus;
use App\Entity\ShopPaymentMethod;
use App\Repository\PaymentRepository;
use App\Service\Checkout\OrderCheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class MonobankWebhookHandler
{
    public function __construct(
        private readonly MonobankWebhookSignatureVerifier $signatureVerifier,
        private readonly PaymentRepository $paymentRepository,
        private readonly OrderCheckoutService $orderCheckoutService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return 'invalid_signature'|'invalid_payload'|'processed'|'ignored'
     */
    public function handle(string $rawBody, ?string $xSignHeader): string
    {
        if (!$this->signatureVerifier->verify($rawBody, $xSignHeader)) {
            $this->logger->warning('Monobank webhook rejected: invalid signature');

            return 'invalid_signature';
        }

        $payload = json_decode($rawBody, true);
        if (!\is_array($payload)) {
            $this->logger->warning('Monobank webhook rejected: invalid JSON payload');

            return 'invalid_payload';
        }

        $payment = $this->resolvePayment($payload);
        if ($payment === null) {
            $this->logger->warning('Monobank webhook: payment not found', [
                'invoiceId' => $payload['invoiceId'] ?? null,
                'reference' => $payload['reference'] ?? null,
            ]);

            return 'processed';
        }

        if ($payment->getMethod() !== ShopPaymentMethod::Monobank) {
            $this->logger->warning('Monobank webhook: payment method mismatch', [
                'payment_id' => $payment->getId(),
                'method' => $payment->getMethod(),
            ]);

            return 'processed';
        }

        $this->ensureMonobankInvoiceId($payment, $payload);

        if ($this->isStaleWebhook($payment, $payload)) {
            $this->logger->info('Monobank webhook ignored: stale modifiedDate', [
                'payment_id' => $payment->getId(),
                'modifiedDate' => $payload['modifiedDate'] ?? null,
            ]);

            return 'ignored';
        }

        $status = (string) ($payload['status'] ?? '');
        match ($status) {
            'success' => $this->orderCheckoutService->markPaymentSuccessful($payment, $payload),
            'failure', 'expired', 'reversed' => $this->orderCheckoutService->markPaymentFailed($payment, $payload),
            default => $this->recordIntermediateStatus($payment, $payload),
        };

        $this->logger->info('Monobank webhook processed', [
            'payment_id' => $payment->getId(),
            'status' => $status,
            'invoiceId' => $payload['invoiceId'] ?? null,
        ]);

        return 'processed';
    }

    /** @param array<string, mixed> $payload */
    private function resolvePayment(array $payload): ?Payment
    {
        $invoiceId = (string) ($payload['invoiceId'] ?? '');
        if ($invoiceId !== '') {
            $payment = $this->paymentRepository->findOneByMonobankInvoiceId($invoiceId);
            if ($payment instanceof Payment) {
                return $payment;
            }
        }

        $reference = (string) ($payload['reference'] ?? $payload['merchantPaymInfo']['reference'] ?? '');
        if ($reference === '') {
            return null;
        }

        return $this->paymentRepository->findOneByGatewayReference($reference);
    }

    /** @param array<string, mixed> $payload */
    private function ensureMonobankInvoiceId(Payment $payment, array $payload): void
    {
        if ($payment->getMonobankInvoiceId() !== null) {
            return;
        }

        $invoiceId = (string) ($payload['invoiceId'] ?? '');
        if ($invoiceId === '') {
            return;
        }

        $payment->setMonobankInvoiceId($invoiceId);
        $this->entityManager->flush();
    }

    /** @param array<string, mixed> $payload */
    private function isStaleWebhook(Payment $payment, array $payload): bool
    {
        $incomingModifiedDate = (string) ($payload['modifiedDate'] ?? '');
        if ($incomingModifiedDate === '') {
            return false;
        }

        $previousResult = $payment->getResultData();
        if (!\is_array($previousResult)) {
            return false;
        }

        $previousModifiedDate = (string) ($previousResult['modifiedDate'] ?? '');
        if ($previousModifiedDate === '') {
            return false;
        }

        return strcmp($incomingModifiedDate, $previousModifiedDate) <= 0
            && $payment->getStatus() !== PaymentStatus::Pending;
    }

    /** @param array<string, mixed> $payload */
    private function recordIntermediateStatus(Payment $payment, array $payload): void
    {
        $payment->setResultData($payload);
        $this->entityManager->flush();
    }
}
