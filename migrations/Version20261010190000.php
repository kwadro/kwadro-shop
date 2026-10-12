<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ProductImport settings: default category, disable categories after import, markup percent';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');

        if (!$table->hasColumn('default_category_id')) {
            $this->addSql('ALTER TABLE shop_product_import ADD default_category_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE shop_product_import ADD CONSTRAINT FK_PRODUCT_IMPORT_DEFAULT_CATEGORY FOREIGN KEY (default_category_id) REFERENCES shop_category (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_PRODUCT_IMPORT_DEFAULT_CATEGORY ON shop_product_import (default_category_id)');
        }

        if (!$table->hasColumn('disable_categories_after_import')) {
            $this->addSql('ALTER TABLE shop_product_import ADD disable_categories_after_import TINYINT(1) DEFAULT 0 NOT NULL');
        }

        if (!$table->hasColumn('disabled_category_slugs')) {
            $this->addSql("ALTER TABLE shop_product_import ADD disabled_category_slugs JSON DEFAULT ('[]') NOT NULL COMMENT '(DC2Type:json)'");
        }

        if (!$table->hasColumn('markup_percent')) {
            $this->addSql("ALTER TABLE shop_product_import ADD markup_percent NUMERIC(8, 2) DEFAULT '0' NOT NULL");
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');

        if ($table->hasColumn('default_category_id')) {
            $this->addSql('ALTER TABLE shop_product_import DROP FOREIGN KEY FK_PRODUCT_IMPORT_DEFAULT_CATEGORY');
            $this->addSql('DROP INDEX IDX_PRODUCT_IMPORT_DEFAULT_CATEGORY ON shop_product_import');
            $this->addSql('ALTER TABLE shop_product_import DROP default_category_id');
        }

        if ($table->hasColumn('disable_categories_after_import')) {
            $this->addSql('ALTER TABLE shop_product_import DROP disable_categories_after_import');
        }

        if ($table->hasColumn('disabled_category_slugs')) {
            $this->addSql('ALTER TABLE shop_product_import DROP disabled_category_slugs');
        }

        if ($table->hasColumn('markup_percent')) {
            $this->addSql('ALTER TABLE shop_product_import DROP markup_percent');
        }
    }
}
