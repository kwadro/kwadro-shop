<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add site products_per_page and category show_filters';
    }

    public function up(Schema $schema): void
    {
        $site = $schema->getTable('site');
        if (!$site->hasColumn('products_per_page')) {
            $this->addSql('ALTER TABLE site ADD products_per_page INT DEFAULT 12 NOT NULL');
        }

        $category = $schema->getTable('shop_category');
        if (!$category->hasColumn('show_filters')) {
            $this->addSql('ALTER TABLE shop_category ADD show_filters TINYINT(1) DEFAULT 1 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $site = $schema->getTable('site');
        if ($site->hasColumn('products_per_page')) {
            $this->addSql('ALTER TABLE site DROP products_per_page');
        }

        $category = $schema->getTable('shop_category');
        if ($category->hasColumn('show_filters')) {
            $this->addSql('ALTER TABLE shop_category DROP show_filters');
        }
    }
}
