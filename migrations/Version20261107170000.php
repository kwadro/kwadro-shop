<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Category name replacements on product import settings';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');
        if (!$table->hasColumn('category_name_replacements')) {
            $this->addSql('ALTER TABLE shop_product_import ADD category_name_replacements JSON DEFAULT NULL');
        }

        $this->addSql('UPDATE shop_product_import SET category_name_replacements = JSON_ARRAY() WHERE category_name_replacements IS NULL');
        $this->addSql('ALTER TABLE shop_product_import MODIFY category_name_replacements JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');
        if ($table->hasColumn('category_name_replacements')) {
            $this->addSql('ALTER TABLE shop_product_import DROP category_name_replacements');
        }
    }
}
