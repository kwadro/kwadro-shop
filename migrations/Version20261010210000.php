<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store ProductImport default category as external_id instead of category PK';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');

        if (!$table->hasColumn('default_category_external_id')) {
            $this->addSql('ALTER TABLE shop_product_import ADD default_category_external_id INT DEFAULT NULL');
        }

        if ($table->hasColumn('default_category_id')) {
            $this->addSql('UPDATE shop_product_import pi
                INNER JOIN shop_category c ON c.id = pi.default_category_id
                SET pi.default_category_external_id = c.external_id
                WHERE pi.default_category_id IS NOT NULL');

            $this->addSql('ALTER TABLE shop_product_import DROP FOREIGN KEY FK_PRODUCT_IMPORT_DEFAULT_CATEGORY');
            $this->addSql('DROP INDEX IDX_PRODUCT_IMPORT_DEFAULT_CATEGORY ON shop_product_import');
            $this->addSql('ALTER TABLE shop_product_import DROP default_category_id');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_product_import');

        if (!$table->hasColumn('default_category_id')) {
            $this->addSql('ALTER TABLE shop_product_import ADD default_category_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE shop_product_import ADD CONSTRAINT FK_PRODUCT_IMPORT_DEFAULT_CATEGORY FOREIGN KEY (default_category_id) REFERENCES shop_category (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_PRODUCT_IMPORT_DEFAULT_CATEGORY ON shop_product_import (default_category_id)');
        }

        if ($table->hasColumn('default_category_external_id')) {
            $this->addSql('UPDATE shop_product_import pi
                INNER JOIN shop_category c ON c.external_id = pi.default_category_external_id
                SET pi.default_category_id = c.id
                WHERE pi.default_category_external_id IS NOT NULL');

            $this->addSql('ALTER TABLE shop_product_import DROP default_category_external_id');
        }
    }
}
