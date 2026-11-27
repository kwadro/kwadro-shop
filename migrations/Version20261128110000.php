<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Homepage section top_title / title / sub_title text fields';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_homepage_setting');

        if (!$table->hasColumn('categories_top_title')) {
            $this->addSql('ALTER TABLE shop_homepage_setting ADD categories_top_title VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('categories_title')) {
            $this->addSql('ALTER TABLE shop_homepage_setting ADD categories_title VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('categories_sub_title')) {
            $this->addSql('ALTER TABLE shop_homepage_setting ADD categories_sub_title LONGTEXT DEFAULT NULL');
        }
        if (!$table->hasColumn('products_top_title')) {
            $this->addSql('ALTER TABLE shop_homepage_setting ADD products_top_title VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('articles_top_title')) {
            $this->addSql('ALTER TABLE shop_homepage_setting ADD articles_top_title VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_homepage_setting');

        if ($table->hasColumn('categories_top_title')) {
            $this->addSql('ALTER TABLE shop_homepage_setting DROP categories_top_title');
        }
        if ($table->hasColumn('categories_title')) {
            $this->addSql('ALTER TABLE shop_homepage_setting DROP categories_title');
        }
        if ($table->hasColumn('categories_sub_title')) {
            $this->addSql('ALTER TABLE shop_homepage_setting DROP categories_sub_title');
        }
        if ($table->hasColumn('products_top_title')) {
            $this->addSql('ALTER TABLE shop_homepage_setting DROP products_top_title');
        }
        if ($table->hasColumn('articles_top_title')) {
            $this->addSql('ALTER TABLE shop_homepage_setting DROP articles_top_title');
        }
    }
}
