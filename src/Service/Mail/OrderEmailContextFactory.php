<?php

namespace App\Service\Mail;

use App\Entity\Order;
use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Service\Checkout\ShipmentPayUrlGenerator;

final class OrderEmailContextFactory
{
    public function __construct(
        private readonly OrderEmailItemsTableRenderer $itemsTableRenderer,
        private readonly ShipmentPayUrlGenerator $shipmentPayUrlGenerator,
        private readonly PaymentRepository $paymentRepository,
    ) {
    }

    /** @return array<string, string> */
    public function create(Order $order, ?Payment $payment = null): array
    {
        $delivery = $order->getDeliveryData();
        $site = $order->getSite() ?? $this->shipmentPayUrlGenerator->resolveSite();
        $prepaymentAmount = $site?->getCodPrepaymentAmount() ?? 0.0;
        $orderData = [
            'shipment_pay_url' => $this->shipmentPayUrlGenerator->generateForOrder($order, $payment),
            'shipment_pay_iban_url' => $this->shipmentPayUrlGenerator->generateIbanUrlForOrder($order, $payment),
            'shipment_prepayment_amount' => $this->formatMoney((float)$prepaymentAmount),
            'order_created_at' => $order->getCreatedAt()->format('d.m.Y, H:i'),
            'order_updated_at' => $order->getUpdatedAt()->format('Y-m-d H:i:s'),
            'order_number' => $order->getOrderNumber(),
            'customer_name' => $order->getCustomerName(),
            'customer_email' => $order->getCustomerEmail() !== ''
                ? $order->getCustomerEmail()
                : trim((string)($order->getCustomer()?->getEmail() ?? '')),
            'customer_phone' => $order->getCustomerPhone(),
            'order_amount' => $this->formatMoney((float)$order->getAmount()),
            'shipping_cost' => $this->formatMoney((float)$order->getShippingCost()),
            'order_total' => $this->formatMoney((float)$order->getAmount()),
            'currency' => $order->getCurrency(),
            'order_status' => $order->getStatus()->value,
            'payment_method' => $payment?->getMethod() ?? '',
            'items_summary' => $order->getItemsSummaryLabel(),
            'order_items_table' => $this->itemsTableRenderer->render($order),
            'delivery_summary' => $this->formatDeliverySummary($delivery),
        ];
        $payments = $this->paymentRepository->findAllByOrder($order);
        $payAmount = 0.00;
        if ($payments) {
            foreach ($payments as $payment) {
                $payAmount += $payment->getAmount();
            }
        }
        $orderData['order_pay_amount'] = $this->formatMoney($payAmount);
        $orderData['order_to_pay_amount'] = $this->formatMoney($order->getAmount() - $payAmount);

        return $orderData;
    }

    /** @param array<string, mixed> $delivery */
    private function formatDeliverySummary(array $delivery): string
    {
        $method = (string)($delivery['deliveryMethod'] ?? '');
        if ($method === 'courier') {
            $address = trim((string)($delivery['courierAddress'] ?? ''));

            return $address !== '' ? sprintf('Курʼєр: %s', $address) : 'Курʼєр';
        }

        if (in_array($method, ['np_branch', 'np_postomat'], true)) {
            $city = trim((string)($delivery['npCityName'] ?? ''));
            $warehouse = trim((string)($delivery['npWarehouseName'] ?? ''));
            $parts = array_values(array_filter([$city, $warehouse], static fn(string $part): bool => $part !== ''));

            return $parts !== [] ? implode(', ', $parts) : 'Нова Пошта';
        }

        return '';
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', ' ');
    }
}
