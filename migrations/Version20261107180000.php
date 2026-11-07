<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember which category slugs the last import disabled';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');
        if (!$table->hasColumn('applied_disabled_category_slugs')) {
            $this->addSql('ALTER TABLE shop_product_import ADD applied_disabled_category_slugs JSON DEFAULT NULL');
        }

        $this->addSql(<<<'SQL'
UPDATE shop_product_import
SET applied_disabled_category_slugs = CASE
    WHEN disable_categories_after_import = 1 THEN disabled_category_slugs
    ELSE JSON_ARRAY()
END
WHERE applied_disabled_category_slugs IS NULL
SQL);
        $this->addSql('ALTER TABLE shop_product_import MODIFY applied_disabled_category_slugs JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');
        if ($table->hasColumn('applied_disabled_category_slugs')) {
            $this->addSql('ALTER TABLE shop_product_import DROP applied_disabled_category_slugs');
        }
    }
}
