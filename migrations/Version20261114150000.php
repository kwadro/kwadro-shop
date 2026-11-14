<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Homepage slider autoplay flags and intervals for hero, categories, products';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_homepage_setting ADD hero_slider_autoplay TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE shop_homepage_setting ADD hero_slider_interval_ms INT DEFAULT 5000 NOT NULL');
        $this->addSql('ALTER TABLE shop_homepage_setting ADD categories_slider_autoplay TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE shop_homepage_setting ADD categories_slider_interval_ms INT DEFAULT 5000 NOT NULL');
        $this->addSql('ALTER TABLE shop_homepage_setting ADD products_slider_autoplay TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE shop_homepage_setting ADD products_slider_interval_ms INT DEFAULT 5000 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_homepage_setting DROP hero_slider_autoplay');
        $this->addSql('ALTER TABLE shop_homepage_setting DROP hero_slider_interval_ms');
        $this->addSql('ALTER TABLE shop_homepage_setting DROP categories_slider_autoplay');
        $this->addSql('ALTER TABLE shop_homepage_setting DROP categories_slider_interval_ms');
        $this->addSql('ALTER TABLE shop_homepage_setting DROP products_slider_autoplay');
        $this->addSql('ALTER TABLE shop_homepage_setting DROP products_slider_interval_ms');
    }
}
