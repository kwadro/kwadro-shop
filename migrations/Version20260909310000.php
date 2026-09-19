<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909310000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product, supplier and product offer tables';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_supplier')) {
            $this->addSql('CREATE TABLE shop_supplier (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, phone VARCHAR(64) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('shop_product')) {
            $this->addSql('CREATE TABLE shop_product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, sku VARCHAR(64) NOT NULL, category VARCHAR(120) NOT NULL, weight NUMERIC(8, 3) DEFAULT 1 NOT NULL, in_stock TINYINT(1) DEFAULT 1 NOT NULL, stock_qty INT DEFAULT 0 NOT NULL, badge VARCHAR(120) DEFAULT NULL, short_description LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, features JSON NOT NULL, gallery JSON NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if (!$schema->hasTable('shop_product_offer')) {
            $this->addSql('CREATE TABLE shop_product_offer (id INT AUTO_INCREMENT NOT NULL, sku VARCHAR(64) NOT NULL, price NUMERIC(12, 2) NOT NULL, old_price NUMERIC(12, 2) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, product_id INT NOT NULL, supplier_id INT NOT NULL, INDEX IDX_shop_product_offer_product (product_id), INDEX IDX_shop_product_offer_supplier (supplier_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE shop_product_offer ADD CONSTRAINT FK_shop_product_offer_product FOREIGN KEY (product_id) REFERENCES shop_product (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE shop_product_offer ADD CONSTRAINT FK_shop_product_offer_supplier FOREIGN KEY (supplier_id) REFERENCES shop_supplier (id) ON DELETE CASCADE');
        }
    }

    public function postUp(Schema $schema): void
    {
        if (!$schema->hasTable('shop_product') || $this->connection->fetchOne('SELECT COUNT(*) FROM shop_product') > 0) {
            return;
        }

        $description = <<<'HTML'
<div>
    <h2>ТВ-антена DVB-T2 Detta 3000</h2>
<p>
    <strong>Detta 3000</strong> — практична телевізійна антена для прийому
    цифрового ефірного телебачення стандарту <strong>DVB-T2</strong>.
    Вона призначена для підключення до телевізора або DVB-T2 тюнера та
    забезпечує стабільний прийом цифрового телевізійного сигналу.
</p>
<h3>Основні переваги</h3>
<ul>
    <li>Підтримка цифрового ефірного телебачення DVB-T2.</li>
    <li>Зручна для використання вдома, у квартирі або на дачі.</li>
    <li>Компактна та практична конструкція.</li>
    <li>Просте підключення до телевізора або DVB-T2 ресивера.</li>
    <li>Не потребує підключення до кабельного телебачення.</li>
</ul>
</div>
HTML;

        $features = json_encode([
            'Потужна ТВ-антена для прийому цифрового сигналу DVB-T2.',
            'Забезпечує стабільний прийом телевізійних каналів у вашому регіоні.',
            'Проста в установці та зручна у використанні.',
            'Відмінний вибір для якісного цифрового телебачення.',
            'Гарантія 12 місяців від виробника',
        ], JSON_UNESCAPED_UNICODE);

        $gallery = json_encode([
            [
                'thumb' => 'https://www.kvadro.if.ua/media/catalog/product/1/1/111142.jpg',
                'full' => 'https://www.kvadro.if.ua/media/catalog/product/1/1/111142.jpg',
                'alt' => 'Тюнер DVB-T2 — фронтальний вигляд',
            ],
            [
                'thumb' => 'https://www.kvadro.if.ua/media/catalog/product/1/1/111142_1.jpg',
                'full' => 'https://www.kvadro.if.ua/media/catalog/product/1/1/111142_1.jpg',
                'alt' => 'Тюнер DVB-T2 — задня панель',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->connection->insert('shop_supplier', [
            'name' => 'Квадро',
            'description' => 'Офіційний постачальник магазину Квадро.',
            'phone' => '066-913-30-97',
            'email' => 'info@kvadro.if.ua',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $supplierId = (int) $this->connection->lastInsertId();

        $this->connection->insert('shop_product', [
            'name' => 'ТВ антена DVB-T2 Delta 3000',
            'sku' => 'Delta-3000',
            'category' => 'ТВ антени',
            'weight' => '0.500',
            'in_stock' => 1,
            'stock_qty' => 14,
            'badge' => 'Хіт продажів',
            'short_description' => 'ТВ антена DVB-T2 Detta 3000 для домашнього телевізора.',
            'description' => $description,
            'features' => $features,
            'gallery' => $gallery,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $productId = (int) $this->connection->lastInsertId();

        $this->connection->insert('shop_product_offer', [
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'sku' => 'Delta-3000',
            'price' => '288.00',
            'old_price' => '360.00',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_product_offer')) {
            $this->addSql('DROP TABLE shop_product_offer');
        }
        if ($schema->hasTable('shop_product')) {
            $this->addSql('DROP TABLE shop_product');
        }
        if ($schema->hasTable('shop_supplier')) {
            $this->addSql('DROP TABLE shop_supplier');
        }
    }
}
