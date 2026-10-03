<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SEO/OG fields to shop_product';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_product');
        if (!$table->hasColumn('title')) {
            $this->addSql('ALTER TABLE shop_product ADD title VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('meta_title')) {
            $this->addSql('ALTER TABLE shop_product ADD meta_title VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('meta_description')) {
            $this->addSql('ALTER TABLE shop_product ADD meta_description LONGTEXT DEFAULT NULL');
        }
        if (!$table->hasColumn('og_title')) {
            $this->addSql('ALTER TABLE shop_product ADD og_title VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('og_description')) {
            $this->addSql('ALTER TABLE shop_product ADD og_description LONGTEXT DEFAULT NULL');
        }
        if (!$table->hasColumn('og_type')) {
            $this->addSql('ALTER TABLE shop_product ADD og_type VARCHAR(64) DEFAULT NULL');
        }
        if (!$table->hasColumn('og_image')) {
            $this->addSql('ALTER TABLE shop_product ADD og_image VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_product');
        foreach (['title', 'meta_title', 'meta_description', 'og_title', 'og_description', 'og_type', 'og_image'] as $column) {
            if ($table->hasColumn($column)) {
                $this->addSql(sprintf('ALTER TABLE shop_product DROP %s', $column));
            }
        }
    }
}
