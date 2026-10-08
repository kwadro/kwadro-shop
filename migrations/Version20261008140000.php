<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product search settings (fields used by catalog search)';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_product_search_setting')) {
            $this->addSql('CREATE TABLE shop_product_search_setting (
                id INT AUTO_INCREMENT NOT NULL,
                site_id INT NOT NULL,
                search_fields JSON NOT NULL,
                UNIQUE INDEX uniq_shop_product_search_setting_site (site_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_PRODUCT_SEARCH_SETTING_SITE FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        $this->addSql(
            "INSERT INTO shop_product_search_setting (site_id, search_fields)
             SELECT s.id, JSON_ARRAY('name', 'model', 'sku')
             FROM site s
             WHERE NOT EXISTS (
                 SELECT 1 FROM shop_product_search_setting pss WHERE pss.site_id = s.id
             )"
        );
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_product_search_setting')) {
            $this->addSql('DROP TABLE shop_product_search_setting');
        }
    }
}
