<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Category publish-to-feed flags and Facebook product feed settings';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['shop_category'])) {
            $table = $sm->introspectTable('shop_category');
            if (!$table->hasColumn('publish_to_feed')) {
                $this->addSql('ALTER TABLE shop_category ADD publish_to_feed TINYINT(1) DEFAULT 0 NOT NULL');
            }
            if (!$table->hasColumn('google_product_category')) {
                $this->addSql('ALTER TABLE shop_category ADD google_product_category VARCHAR(255) DEFAULT NULL');
            }
            if (!$table->hasColumn('fb_product_category')) {
                $this->addSql('ALTER TABLE shop_category ADD fb_product_category VARCHAR(255) DEFAULT NULL');
            }
        }

        if (!$schema->hasTable('shop_product_feed_setting') && !$sm->tablesExist(['shop_product_feed_setting'])) {
            $this->addSql('CREATE TABLE shop_product_feed_setting (
                id INT AUTO_INCREMENT NOT NULL,
                site_id INT NOT NULL,
                enabled TINYINT(1) DEFAULT 1 NOT NULL,
                access_token VARCHAR(64) DEFAULT NULL,
                format VARCHAR(8) DEFAULT \'csv\' NOT NULL,
                locale_code VARCHAR(8) DEFAULT \'uk\' NOT NULL,
                currency VARCHAR(3) DEFAULT \'UAH\' NOT NULL,
                default_brand VARCHAR(100) DEFAULT NULL,
                item_condition VARCHAR(32) DEFAULT \'new\' NOT NULL,
                default_google_product_category VARCHAR(255) DEFAULT NULL,
                default_fb_product_category VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX uniq_shop_product_feed_setting_site (site_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE shop_product_feed_setting ADD CONSTRAINT FK_product_feed_setting_site FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['shop_product_feed_setting'])) {
            $this->addSql('ALTER TABLE shop_product_feed_setting DROP FOREIGN KEY FK_product_feed_setting_site');
            $this->addSql('DROP TABLE shop_product_feed_setting');
        }

        if ($sm->tablesExist(['shop_category'])) {
            $table = $sm->introspectTable('shop_category');
            if ($table->hasColumn('fb_product_category')) {
                $this->addSql('ALTER TABLE shop_category DROP fb_product_category');
            }
            if ($table->hasColumn('google_product_category')) {
                $this->addSql('ALTER TABLE shop_category DROP google_product_category');
            }
            if ($table->hasColumn('publish_to_feed')) {
                $this->addSql('ALTER TABLE shop_category DROP publish_to_feed');
            }
        }
    }
}
