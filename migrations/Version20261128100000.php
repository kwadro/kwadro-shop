<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shop_product.supplier_delete flag (missing from supplier feed)';
    }

    public function up(Schema $schema): void
    {
        $product = $schema->getTable('shop_product');
        if (!$product->hasColumn('supplier_delete')) {
            $this->addSql('ALTER TABLE shop_product ADD supplier_delete TINYINT(1) DEFAULT 0 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $product = $schema->getTable('shop_product');
        if ($product->hasColumn('supplier_delete')) {
            $this->addSql('ALTER TABLE shop_product DROP supplier_delete');
        }
    }
}
