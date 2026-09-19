<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop_order and shop_payment tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_order (id INT AUTO_INCREMENT NOT NULL, order_number VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, visitor_id VARCHAR(36) DEFAULT NULL, contact_data JSON NOT NULL, delivery_data JSON NOT NULL, cart_data JSON NOT NULL, amount NUMERIC(12, 2) NOT NULL, currency VARCHAR(3) NOT NULL, locale VARCHAR(5) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, customer_id INT DEFAULT NULL, UNIQUE INDEX uniq_shop_order_number (order_number), INDEX IDX_shop_order_customer (customer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE shop_payment (id INT AUTO_INCREMENT NOT NULL, method VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL, amount NUMERIC(12, 2) NOT NULL, currency VARCHAR(3) NOT NULL, gateway_reference VARCHAR(128) DEFAULT NULL, redirect_url LONGTEXT DEFAULT NULL, gateway_response JSON DEFAULT NULL, result_data JSON DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, order_id INT NOT NULL, INDEX IDX_shop_payment_order (order_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE shop_order ADD CONSTRAINT FK_shop_order_customer FOREIGN KEY (customer_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE shop_payment ADD CONSTRAINT FK_shop_payment_order FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_payment DROP FOREIGN KEY FK_shop_payment_order');
        $this->addSql('ALTER TABLE shop_order DROP FOREIGN KEY FK_shop_order_customer');
        $this->addSql('DROP TABLE shop_payment');
        $this->addSql('DROP TABLE shop_order');
    }
}
