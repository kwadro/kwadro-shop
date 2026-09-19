<?php

namespace App\Service\Mail;

use App\Entity\Order;
use App\Entity\OrderItem;

final class OrderEmailItemsTableRenderer
{
    public function render(Order $order): string
    {
        $rows = $this->collectRows($order);
        if ($rows === []) {
            return '';
        }

        $blocks = [];
        $lastIndex = \count($rows) - 1;
        foreach ($rows as $index => $row) {
            $border = $index < $lastIndex ? 'border-bottom:1px solid #eef2f7;' : '';
            $sku = $row['sku'] !== '' ? $this->escape($row['sku']) . ' · ' : '';

            $blocks[] = sprintf(
                '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" '
                . 'style="border-collapse:collapse;%s">'
                . '<tr><td style="padding:9px 0;font-family:\'Segoe UI\',Arial,sans-serif;">'
                . '<p style="margin:0 0 3px;font-size:13px;line-height:1.45;font-weight:600;color:#1e293b;">%s</p>'
                . '<p style="margin:0;font-size:12px;line-height:1.5;color:#64748b;">'
                . '%s%s шт × %s = <strong style="color:#1e293b;">%s</strong>'
                . '</p>'
                . '</td></tr></table>',
                $border,
                $this->escape($row['name']),
                $sku,
                $this->escape($row['quantity']),
                $this->escape($row['unit_price']),
                $this->escape($row['line_total']),
            );
        }

        return implode('', $blocks);
    }

    /** @return list<array{name: string, sku: string, quantity: string, unit_price: string, line_total: string}> */
    private function collectRows(Order $order): array
    {
        if (!$order->getItems()->isEmpty()) {
            $rows = [];
            foreach ($order->getItems() as $item) {
                if (!$item instanceof OrderItem) {
                    continue;
                }

                $rows[] = $this->rowFromOrderItem($item);
            }

            return $rows;
        }

        $cartData = $order->getCartData();
        $cartItems = $cartData['items'] ?? null;
        if (\is_array($cartItems) && $cartItems !== []) {
            $rows = [];
            foreach ($cartItems as $cartItem) {
                if (!\is_array($cartItem)) {
                    continue;
                }

                $rows[] = $this->rowFromCartItem($cartItem);
            }

            return array_values(array_filter($rows, static fn (array $row): bool => $row['name'] !== ''));
        }

        $product = $cartData['product'] ?? null;
        if (!\is_array($product)) {
            return [];
        }

        $quantity = max(1, (int) ($cartData['quantity'] ?? 1));
        $unitPrice = (float) ($product['price'] ?? 0);

        return [[
            'name' => trim((string) ($product['name'] ?? '')),
            'sku' => $this->resolveSku($product),
            'quantity' => (string) $quantity,
            'unit_price' => $this->formatMoney($unitPrice),
            'line_total' => $this->formatMoney($unitPrice * $quantity),
        ]];
    }

    /** @return array{name: string, sku: string, quantity: string, unit_price: string, line_total: string} */
    private function rowFromOrderItem(OrderItem $item): array
    {
        $snapshot = $item->getProductSnapshot();

        return [
            'name' => $item->getProductName(),
            'sku' => $item->getProductSku() !== '' ? $item->getProductSku() : $this->resolveSku($snapshot),
            'quantity' => (string) $item->getQuantity(),
            'unit_price' => $this->formatMoney($item->getUnitPrice()),
            'line_total' => $this->formatMoney($item->getLineTotal()),
        ];
    }

    /** @param array<string, mixed> $cartItem */
    /** @return array{name: string, sku: string, quantity: string, unit_price: string, line_total: string} */
    private function rowFromCartItem(array $cartItem): array
    {
        $product = \is_array($cartItem['product'] ?? null) ? $cartItem['product'] : [];
        $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));
        $unitPrice = (float) ($product['price'] ?? 0);

        return [
            'name' => trim((string) ($product['name'] ?? '')),
            'sku' => $this->resolveSku($product),
            'quantity' => (string) $quantity,
            'unit_price' => $this->formatMoney($unitPrice),
            'line_total' => $this->formatMoney($unitPrice * $quantity),
        ];
    }

    /** @param array<string, mixed> $product */
    private function resolveSku(array $product): string
    {
        return trim((string) ($product['offerSku'] ?? $product['sku'] ?? ''));
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' ₴';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
