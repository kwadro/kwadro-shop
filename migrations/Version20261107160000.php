<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Menu visibility overrides on product import settings';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');
        if (!$table->hasColumn('menu_category_visibility')) {
            $this->addSql('ALTER TABLE shop_product_import ADD menu_category_visibility JSON DEFAULT NULL');
        }

        $this->addSql('UPDATE shop_product_import SET menu_category_visibility = JSON_ARRAY() WHERE menu_category_visibility IS NULL');
        $this->addSql('ALTER TABLE shop_product_import MODIFY menu_category_visibility JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');
        if ($table->hasColumn('menu_category_visibility')) {
            $this->addSql('ALTER TABLE shop_product_import DROP menu_category_visibility');
        }
    }
}
