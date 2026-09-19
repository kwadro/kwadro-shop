<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill saved shipment addresses for existing customer orders';
    }

    public function up(Schema $schema): void
    {
    }

    public function down(Schema $schema): void
    {
    }

    public function postUp(Schema $schema): void
    {
        $orders = $this->connection->fetchAllAssociative(
            'SELECT o.id AS order_id, o.customer_id, o.delivery_data, sa.id AS order_address_id, sa.type, sa.courier_address, sa.np_city_ref, sa.np_city_name, sa.np_warehouse_ref, sa.np_warehouse_name, sa.delivery_cost, sa.label
             FROM shop_order o
             LEFT JOIN shop_shipment_address sa ON sa.order_id = o.id
             WHERE o.customer_id IS NOT NULL
             ORDER BY o.id ASC'
        );

        foreach ($orders as $order) {
            $customerId = (int) ($order['customer_id'] ?? 0);
            if ($customerId <= 0) {
                continue;
            }

            $source = $this->buildSourceRow($order);
            if ($source === null) {
                continue;
            }

            if ($this->findMatchingSavedAddress($customerId, $source) !== null) {
                continue;
            }

            $isDefault = !$this->hasSavedAddressForUser($customerId);

            $this->connection->insert('shop_shipment_address', [
                'type' => $source['type'],
                'courier_address' => $source['courier_address'],
                'np_city_ref' => $source['np_city_ref'],
                'np_city_name' => $source['np_city_name'],
                'np_warehouse_ref' => $source['np_warehouse_ref'],
                'np_warehouse_name' => $source['np_warehouse_name'],
                'label' => $source['label'],
                'is_default' => $isDefault ? 1 : 0,
                'delivery_cost' => $source['delivery_cost'],
                'user_id' => $customerId,
                'cart_id' => null,
                'order_id' => null,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /** @param array<string, mixed> $order */
    /** @return array<string, mixed>|null */
    private function buildSourceRow(array $order): ?array
    {
        if (($order['order_address_id'] ?? null) !== null) {
            return [
                'type' => (string) $order['type'],
                'courier_address' => $order['courier_address'],
                'np_city_ref' => $order['np_city_ref'],
                'np_city_name' => $order['np_city_name'],
                'np_warehouse_ref' => $order['np_warehouse_ref'],
                'np_warehouse_name' => $order['np_warehouse_name'],
                'delivery_cost' => $order['delivery_cost'],
                'label' => $this->buildLabel(
                    (string) $order['type'],
                    $order['courier_address'],
                    $order['np_city_name'],
                    $order['np_warehouse_name'],
                    $order['label'],
                ),
            ];
        }

        $delivery = json_decode((string) ($order['delivery_data'] ?? ''), true);
        if (!\is_array($delivery) || ($delivery['deliveryMethod'] ?? '') === '') {
            return null;
        }

        return [
            'type' => (string) $delivery['deliveryMethod'],
            'courier_address' => $delivery['courierAddress'] ?? null,
            'np_city_ref' => $delivery['npCityRef'] ?? null,
            'np_city_name' => $delivery['npCityName'] ?? null,
            'np_warehouse_ref' => $delivery['npWarehouseRef'] ?? null,
            'np_warehouse_name' => $delivery['npWarehouseName'] ?? null,
            'delivery_cost' => isset($delivery['deliveryCost']) && is_numeric($delivery['deliveryCost'])
                ? number_format((float) $delivery['deliveryCost'], 2, '.', '')
                : null,
            'label' => $this->buildLabel(
                (string) $delivery['deliveryMethod'],
                $delivery['courierAddress'] ?? null,
                $delivery['npCityName'] ?? null,
                $delivery['npWarehouseName'] ?? null,
                null,
            ),
        ];
    }

    /** @param array<string, mixed> $source */
    private function findMatchingSavedAddress(int $customerId, array $source): ?int
    {
        $savedAddresses = $this->connection->fetchAllAssociative(
            'SELECT id, type, courier_address, np_city_ref, np_warehouse_ref
             FROM shop_shipment_address
             WHERE user_id = :userId AND cart_id IS NULL AND order_id IS NULL',
            ['userId' => $customerId],
        );

        foreach ($savedAddresses as $saved) {
            if (($saved['type'] ?? '') !== ($source['type'] ?? '')) {
                continue;
            }

            if (($source['type'] ?? '') === 'courier') {
                if ($this->normalizeText($saved['courier_address']) === $this->normalizeText($source['courier_address'])) {
                    return (int) $saved['id'];
                }

                continue;
            }

            if (($saved['np_city_ref'] ?? '') === ($source['np_city_ref'] ?? '')
                && ($saved['np_warehouse_ref'] ?? '') === ($source['np_warehouse_ref'] ?? '')) {
                return (int) $saved['id'];
            }
        }

        return null;
    }

    private function hasSavedAddressForUser(int $customerId): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM shop_shipment_address WHERE user_id = :userId AND cart_id IS NULL AND order_id IS NULL',
            ['userId' => $customerId],
        ) > 0;
    }

    private function buildLabel(
        string $type,
        mixed $courierAddress,
        mixed $npCityName,
        mixed $npWarehouseName,
        mixed $existingLabel,
    ): ?string {
        if (\is_string($existingLabel) && trim($existingLabel) !== '') {
            return trim($existingLabel);
        }

        if ($type === 'courier') {
            $address = trim((string) $courierAddress);

            return $address !== '' ? $address : null;
        }

        $label = trim(sprintf('%s, %s', (string) $npCityName, (string) $npWarehouseName), ', ');

        return $label !== '' ? $label : null;
    }

    private function normalizeText(mixed $value): string
    {
        if (!\is_string($value) && !is_numeric($value)) {
            return '';
        }

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));
    }
}
