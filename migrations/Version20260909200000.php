<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add delivery cost to shipment addresses and shipping cost to orders';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_shipment_address') && !$schema->getTable('shop_shipment_address')->hasColumn('delivery_cost')) {
            $this->addSql('ALTER TABLE shop_shipment_address ADD delivery_cost NUMERIC(12, 2) DEFAULT NULL');
        }

        if ($schema->hasTable('shop_order') && !$schema->getTable('shop_order')->hasColumn('shipping_cost')) {
            $this->addSql('ALTER TABLE shop_order ADD shipping_cost NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_shipment_address') && $schema->getTable('shop_shipment_address')->hasColumn('delivery_cost')) {
            $this->addSql('ALTER TABLE shop_shipment_address DROP delivery_cost');
        }

        if ($schema->hasTable('shop_order') && $schema->getTable('shop_order')->hasColumn('shipping_cost')) {
            $this->addSql('ALTER TABLE shop_order DROP shipping_cost');
        }
    }
}
