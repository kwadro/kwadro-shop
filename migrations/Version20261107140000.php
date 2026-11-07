<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Menu display overrides on product import: pinned categories and parent moves';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');
        if (!$table->hasColumn('menu_top_categories')) {
            $this->addSql('ALTER TABLE shop_product_import ADD menu_top_categories JSON DEFAULT NULL');
        }
        if (!$table->hasColumn('menu_category_moves')) {
            $this->addSql('ALTER TABLE shop_product_import ADD menu_category_moves JSON DEFAULT NULL');
        }
        $this->addSql("UPDATE shop_product_import SET menu_top_categories = JSON_ARRAY() WHERE menu_top_categories IS NULL");
        $this->addSql("UPDATE shop_product_import SET menu_category_moves = JSON_ARRAY() WHERE menu_category_moves IS NULL");

        $this->addSql(<<<'SQL'
UPDATE shop_product_import i
SET i.menu_category_moves = JSON_ARRAY(
    JSON_OBJECT(
        'category_id', (SELECT c.id FROM shop_category c WHERE c.slug = 'syta-ta-drushlyaky' LIMIT 1),
        'parent_id', (SELECT p.id FROM shop_category p WHERE p.slug = 'posud-dlya-pryhotuvannya' LIMIT 1)
    )
)
WHERE i.code = 'sivitek'
  AND EXISTS (SELECT 1 FROM shop_category c WHERE c.slug = 'syta-ta-drushlyaky')
  AND EXISTS (SELECT 1 FROM shop_category p WHERE p.slug = 'posud-dlya-pryhotuvannya')
SQL);
        $this->addSql('ALTER TABLE shop_product_import MODIFY menu_top_categories JSON NOT NULL');
        $this->addSql('ALTER TABLE shop_product_import MODIFY menu_category_moves JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');
        if ($table->hasColumn('menu_category_moves')) {
            $this->addSql('ALTER TABLE shop_product_import DROP menu_category_moves');
        }
        if ($table->hasColumn('menu_top_categories')) {
            $this->addSql('ALTER TABLE shop_product_import DROP menu_top_categories');
        }
    }
}
