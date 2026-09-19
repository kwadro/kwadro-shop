<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add qty stock field to product offers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product_offer ADD qty INT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE shop_product_offer po INNER JOIN shop_product p ON p.id = po.product_id SET po.qty = p.stock_qty WHERE po.qty = 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product_offer DROP qty');
    }
}
