<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attribute name replacements on product import settings';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');
        if (!$table->hasColumn('param_name_replacements')) {
            $this->addSql('ALTER TABLE shop_product_import ADD param_name_replacements JSON DEFAULT NULL');
        }

        $this->addSql('UPDATE shop_product_import SET param_name_replacements = JSON_ARRAY() WHERE param_name_replacements IS NULL');
        $this->addSql('ALTER TABLE shop_product_import MODIFY param_name_replacements JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');
        if ($table->hasColumn('param_name_replacements')) {
            $this->addSql('ALTER TABLE shop_product_import DROP param_name_replacements');
        }
    }
}
