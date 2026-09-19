<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cart/order item entities, cart status, migrate legacy JSON cart data';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_cart_item')) {
            $this->addSql('CREATE TABLE shop_cart_item (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL, product_name VARCHAR(255) NOT NULL, product_sku VARCHAR(64) NOT NULL, unit_price NUMERIC(12, 2) NOT NULL, product_snapshot JSON NOT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, cart_id INT NOT NULL, INDEX IDX_shop_cart_item_cart (cart_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE shop_cart_item ADD CONSTRAINT FK_shop_cart_item_cart FOREIGN KEY (cart_id) REFERENCES shop_cart (id) ON DELETE CASCADE');
        }

        if (!$schema->hasTable('shop_order_item')) {
            $this->addSql('CREATE TABLE shop_order_item (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL, product_name VARCHAR(255) NOT NULL, product_sku VARCHAR(64) NOT NULL, unit_price NUMERIC(12, 2) NOT NULL, line_total NUMERIC(12, 2) NOT NULL, product_snapshot JSON NOT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, order_id INT NOT NULL, INDEX IDX_shop_order_item_order (order_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE shop_order_item ADD CONSTRAINT FK_shop_order_item_order FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE CASCADE');
        }

        if (!$schema->getTable('shop_cart')->hasColumn('status')) {
            $this->addSql('ALTER TABLE shop_cart ADD status VARCHAR(32) NOT NULL DEFAULT \'active\'');
        }

        $this->relaxCartUniqueIndexes();
    }

    public function postUp(Schema $schema): void
    {
        if ((int) $this->connection->fetchOne('SELECT COUNT(*) FROM shop_cart_item') === 0) {
            $this->migrateCartItems();
        }

        if ((int) $this->connection->fetchOne('SELECT COUNT(*) FROM shop_order_item') === 0) {
            $this->migrateOrderItems();
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_cart_item DROP FOREIGN KEY FK_shop_cart_item_cart');
        $this->addSql('ALTER TABLE shop_order_item DROP FOREIGN KEY FK_shop_order_item_order');
        $this->addSql('DROP TABLE shop_cart_item');
        $this->addSql('DROP TABLE shop_order_item');
        $this->addSql('DROP INDEX IDX_shop_cart_visitor_status ON shop_cart');
        $this->addSql('DROP INDEX IDX_shop_cart_customer_status ON shop_cart');
        $this->addSql('CREATE UNIQUE INDEX uniq_cart_visitor_id ON shop_cart (visitor_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cart_customer_id ON shop_cart (customer_id)');
        $this->addSql('ALTER TABLE shop_cart DROP status');
    }

    private function relaxCartUniqueIndexes(): void
    {
        $indexes = $this->connection->fetchAllAssociative('SHOW INDEX FROM shop_cart');
        $indexNames = array_column($indexes, 'Key_name');

        if (\in_array('uniq_cart_customer_id', $indexNames, true)) {
            $this->addSql('ALTER TABLE shop_cart DROP FOREIGN KEY FK_CA516ECC9395C3F3');
            $this->addSql('DROP INDEX uniq_cart_customer_id ON shop_cart');
            $this->addSql('ALTER TABLE shop_cart ADD CONSTRAINT FK_CA516ECC9395C3F3 FOREIGN KEY (customer_id) REFERENCES app_user (id) ON DELETE CASCADE');
        }

        if (\in_array('uniq_cart_visitor_id', $indexNames, true)) {
            $this->addSql('DROP INDEX uniq_cart_visitor_id ON shop_cart');
        }

        if (!\in_array('IDX_shop_cart_visitor_status', $indexNames, true)) {
            $this->addSql('CREATE INDEX IDX_shop_cart_visitor_status ON shop_cart (visitor_id, status)');
        }

        if (!\in_array('IDX_shop_cart_customer_status', $indexNames, true)) {
            $this->addSql('CREATE INDEX IDX_shop_cart_customer_status ON shop_cart (customer_id, status)');
        }
    }

    private function migrateCartItems(): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, cart_data FROM shop_cart WHERE cart_data IS NOT NULL');

        foreach ($rows as $row) {
            $cartData = json_decode((string) $row['cart_data'], true);
            if (!\is_array($cartData) || empty($cartData['product']) || !\is_array($cartData['product'])) {
                continue;
            }

            $product = $cartData['product'];
            $quantity = max(1, (int) ($cartData['quantity'] ?? 1));
            $unitPrice = number_format((float) ($product['price'] ?? 0), 2, '.', '');

            $this->connection->executeStatement(
                'INSERT INTO shop_cart_item (cart_id, product_id, quantity, product_name, product_sku, unit_price, product_snapshot, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    (int) $row['id'],
                    (int) ($cartData['product_id'] ?? $product['id'] ?? 0),
                    $quantity,
                    (string) ($product['name'] ?? ''),
                    (string) ($product['sku'] ?? ''),
                    $unitPrice,
                    json_encode($product, JSON_UNESCAPED_UNICODE),
                    'active',
                ],
            );
        }
    }

    private function migrateOrderItems(): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, cart_data, status FROM shop_order WHERE cart_data IS NOT NULL');

        foreach ($rows as $row) {
            $cartData = json_decode((string) $row['cart_data'], true);
            if (!\is_array($cartData) || empty($cartData['product']) || !\is_array($cartData['product'])) {
                continue;
            }

            $product = $cartData['product'];
            $quantity = max(1, (int) ($cartData['quantity'] ?? 1));
            $unitPrice = (float) ($product['price'] ?? 0);
            $lineTotal = number_format($unitPrice * $quantity, 2, '.', '');
            $itemStatus = ((string) ($row['status'] ?? '')) === 'paid' ? 'confirmed' : 'pending';

            $this->connection->executeStatement(
                'INSERT INTO shop_order_item (order_id, product_id, quantity, product_name, product_sku, unit_price, line_total, product_snapshot, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    (int) $row['id'],
                    (int) ($cartData['product_id'] ?? $product['id'] ?? 0),
                    $quantity,
                    (string) ($product['name'] ?? ''),
                    (string) ($product['sku'] ?? ''),
                    number_format($unitPrice, 2, '.', ''),
                    $lineTotal,
                    json_encode($product, JSON_UNESCAPED_UNICODE),
                    $itemStatus,
                ],
            );
        }
    }
}
