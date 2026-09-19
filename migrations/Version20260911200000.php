<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260911200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add offer and supplier fields to cart and order items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_cart_item ADD offer_id INT DEFAULT NULL, ADD supplier_id INT DEFAULT NULL, ADD supplier_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE shop_order_item ADD offer_id INT DEFAULT NULL, ADD supplier_id INT DEFAULT NULL, ADD supplier_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_cart_item DROP offer_id, DROP supplier_id, DROP supplier_name');
        $this->addSql('ALTER TABLE shop_order_item DROP offer_id, DROP supplier_id, DROP supplier_name');
    }
}
