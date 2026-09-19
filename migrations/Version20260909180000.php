<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shipment address entity linked to cart, order and user saved addresses';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_shipment_address')) {
            $this->addSql('CREATE TABLE shop_shipment_address (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(32) NOT NULL, courier_address LONGTEXT DEFAULT NULL, np_city_ref VARCHAR(64) DEFAULT NULL, np_city_name VARCHAR(255) DEFAULT NULL, np_warehouse_ref VARCHAR(64) DEFAULT NULL, np_warehouse_name VARCHAR(255) DEFAULT NULL, label VARCHAR(120) DEFAULT NULL, is_default TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, cart_id INT DEFAULT NULL, order_id INT DEFAULT NULL, user_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_shop_shipment_address_cart (cart_id), UNIQUE INDEX UNIQ_shop_shipment_address_order (order_id), INDEX IDX_shop_shipment_address_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE shop_shipment_address ADD CONSTRAINT FK_shop_shipment_address_cart FOREIGN KEY (cart_id) REFERENCES shop_cart (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE shop_shipment_address ADD CONSTRAINT FK_shop_shipment_address_order FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE shop_shipment_address ADD CONSTRAINT FK_shop_shipment_address_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        }
    }

    public function postUp(Schema $schema): void
    {
        if (!$schema->hasTable('shop_shipment_address')) {
            return;
        }

        if ((int) $this->connection->fetchOne('SELECT COUNT(*) FROM shop_shipment_address') > 0) {
            return;
        }

        $this->migrateCartDeliveryAddresses();
        $this->migrateOrderDeliveryAddresses();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_shipment_address DROP FOREIGN KEY FK_shop_shipment_address_cart');
        $this->addSql('ALTER TABLE shop_shipment_address DROP FOREIGN KEY FK_shop_shipment_address_order');
        $this->addSql('ALTER TABLE shop_shipment_address DROP FOREIGN KEY FK_shop_shipment_address_user');
        $this->addSql('DROP TABLE shop_shipment_address');
    }

    private function migrateCartDeliveryAddresses(): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, delivery_data FROM shop_cart WHERE delivery_data IS NOT NULL');

        foreach ($rows as $row) {
            $delivery = json_decode((string) $row['delivery_data'], true);
            if (!\is_array($delivery) || ($delivery['deliveryMethod'] ?? '') === '') {
                continue;
            }

            $this->connection->insert('shop_shipment_address', $this->buildAddressRow($delivery) + [
                'cart_id' => (int) $row['id'],
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    private function migrateOrderDeliveryAddresses(): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, delivery_data FROM shop_order WHERE delivery_data IS NOT NULL');

        foreach ($rows as $row) {
            $delivery = json_decode((string) $row['delivery_data'], true);
            if (!\is_array($delivery) || ($delivery['deliveryMethod'] ?? '') === '') {
                continue;
            }

            $this->connection->insert('shop_shipment_address', $this->buildAddressRow($delivery) + [
                'order_id' => (int) $row['id'],
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /** @param array<string, mixed> $delivery */
    /** @return array<string, mixed> */
    private function buildAddressRow(array $delivery): array
    {
        return [
            'type' => (string) ($delivery['deliveryMethod'] ?? 'courier'),
            'courier_address' => $delivery['courierAddress'] ?? null,
            'np_city_ref' => $delivery['npCityRef'] ?? null,
            'np_city_name' => $delivery['npCityName'] ?? null,
            'np_warehouse_ref' => $delivery['npWarehouseRef'] ?? null,
            'np_warehouse_name' => $delivery['npWarehouseName'] ?? null,
            'label' => null,
            'is_default' => 0,
            'user_id' => null,
        ];
    }
}
