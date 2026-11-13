<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix homepage top-products join PK/position; add Google review Place ID on site';
    }

    public function up(Schema $schema): void
    {
        // OrderColumn inserts can omit position under MySQL STRICT_TRANS_TABLES when
        // the PK is (homepage_setting_id, position). Use product_id as PK instead.
        $this->addSql('ALTER TABLE shop_homepage_setting_product_top DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE shop_homepage_setting_product_top MODIFY position INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE shop_homepage_setting_product_top ADD PRIMARY KEY (homepage_setting_id, product_id)');

        $this->addSql('ALTER TABLE site ADD google_place_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE site ADD google_review_url VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE site ADD google_review_enabled TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site DROP google_place_id, DROP google_review_url, DROP google_review_enabled');

        $this->addSql('ALTER TABLE shop_homepage_setting_product_top DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE shop_homepage_setting_product_top MODIFY position INT NOT NULL');
        $this->addSql('ALTER TABLE shop_homepage_setting_product_top ADD PRIMARY KEY (homepage_setting_id, position)');
    }
}
