<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Product import: optional strip of feed SKU from product name (enabled for lugi)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product_import ADD strip_sku_from_name TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE shop_product_import SET strip_sku_from_name = 1 WHERE code = 'lugi'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product_import DROP strip_sku_from_name');
    }
}
