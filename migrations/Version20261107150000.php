<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attribute value replacements on product import settings';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');
        if (!$table->hasColumn('param_value_replacements')) {
            $this->addSql('ALTER TABLE shop_product_import ADD param_value_replacements JSON DEFAULT NULL');
        }

        $this->addSql("UPDATE shop_product_import SET param_value_replacements = JSON_ARRAY() WHERE param_value_replacements IS NULL");
        $this->addSql(<<<'SQL'
UPDATE shop_product_import
SET param_value_replacements = JSON_ARRAY(
    JSON_OBJECT('from', 'Фыолетовий', 'to', 'Фіолетовий'),
    JSON_OBJECT('from', 'Брелоки', 'to', 'Брелки')
)
WHERE code = 'lugi'
  AND (param_value_replacements IS NULL OR JSON_LENGTH(param_value_replacements) = 0)
SQL);
        $this->addSql('ALTER TABLE shop_product_import MODIFY param_value_replacements JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');
        if ($table->hasColumn('param_value_replacements')) {
            $this->addSql('ALTER TABLE shop_product_import DROP param_value_replacements');
        }
    }
}
