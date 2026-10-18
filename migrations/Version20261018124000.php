<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018124000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Homepage categories block settings (enable, source, per-row, selected categories)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_homepage_setting ADD categories_enabled TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql("ALTER TABLE shop_homepage_setting ADD categories_source VARCHAR(20) DEFAULT 'latest' NOT NULL");
        $this->addSql('ALTER TABLE shop_homepage_setting ADD categories_per_row INT DEFAULT 3 NOT NULL');
        $this->addSql('CREATE TABLE shop_homepage_setting_category (
            homepage_setting_id INT NOT NULL,
            category_id INT NOT NULL,
            INDEX IDX_HSC_SETTING (homepage_setting_id),
            INDEX IDX_HSC_CATEGORY (category_id),
            PRIMARY KEY(homepage_setting_id, category_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE shop_homepage_setting_category ADD CONSTRAINT FK_HSC_SETTING FOREIGN KEY (homepage_setting_id) REFERENCES shop_homepage_setting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_homepage_setting_category ADD CONSTRAINT FK_HSC_CATEGORY FOREIGN KEY (category_id) REFERENCES shop_category (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_homepage_setting_category DROP FOREIGN KEY FK_HSC_SETTING');
        $this->addSql('ALTER TABLE shop_homepage_setting_category DROP FOREIGN KEY FK_HSC_CATEGORY');
        $this->addSql('DROP TABLE shop_homepage_setting_category');
        $this->addSql('ALTER TABLE shop_homepage_setting DROP categories_enabled, DROP categories_source, DROP categories_per_row');
    }
}
