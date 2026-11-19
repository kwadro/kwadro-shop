<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119212000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Homepage promo strip settings and HTML ticker items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_homepage_setting ADD promo_strip_enabled TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE shop_homepage_setting ADD promo_strip_dismissible TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE shop_homepage_setting ADD promo_strip_interval_ms INT DEFAULT 5000 NOT NULL');
        $this->addSql('CREATE TABLE shop_homepage_promo_strip_item (
            id INT AUTO_INCREMENT NOT NULL,
            homepage_setting_id INT NOT NULL,
            enabled TINYINT(1) DEFAULT 1 NOT NULL,
            position INT DEFAULT 0 NOT NULL,
            content LONGTEXT NOT NULL,
            INDEX IDX_HPS_PROMO_STRIP_SETTING (homepage_setting_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE shop_homepage_promo_strip_item ADD CONSTRAINT FK_HPS_PROMO_STRIP_SETTING FOREIGN KEY (homepage_setting_id) REFERENCES shop_homepage_setting (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_homepage_promo_strip_item DROP FOREIGN KEY FK_HPS_PROMO_STRIP_SETTING');
        $this->addSql('DROP TABLE shop_homepage_promo_strip_item');
        $this->addSql('ALTER TABLE shop_homepage_setting DROP promo_strip_enabled');
        $this->addSql('ALTER TABLE shop_homepage_setting DROP promo_strip_dismissible');
        $this->addSql('ALTER TABLE shop_homepage_setting DROP promo_strip_interval_ms');
    }
}
