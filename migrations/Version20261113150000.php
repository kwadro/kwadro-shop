<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Homepage setting: ordered top products for leading places in New offers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_homepage_setting_product_top (
            homepage_setting_id INT NOT NULL,
            product_id INT NOT NULL,
            position INT NOT NULL,
            INDEX IDX_HPS_PRODUCT_TOP_SETTING (homepage_setting_id),
            INDEX IDX_HPS_PRODUCT_TOP_PRODUCT (product_id),
            PRIMARY KEY (homepage_setting_id, position)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE shop_homepage_setting_product_top ADD CONSTRAINT FK_HPS_PRODUCT_TOP_SETTING FOREIGN KEY (homepage_setting_id) REFERENCES shop_homepage_setting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_homepage_setting_product_top ADD CONSTRAINT FK_HPS_PRODUCT_TOP_PRODUCT FOREIGN KEY (product_id) REFERENCES shop_product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_homepage_setting_product_top DROP FOREIGN KEY FK_HPS_PRODUCT_TOP_SETTING');
        $this->addSql('ALTER TABLE shop_homepage_setting_product_top DROP FOREIGN KEY FK_HPS_PRODUCT_TOP_PRODUCT');
        $this->addSql('DROP TABLE shop_homepage_setting_product_top');
    }
}
