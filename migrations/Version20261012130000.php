<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Homepage block settings per site and locale (new offers, interesting articles)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_homepage_setting (
            id INT AUTO_INCREMENT NOT NULL,
            products_enabled TINYINT(1) DEFAULT 1 NOT NULL,
            products_title VARCHAR(255) DEFAULT NULL,
            products_lead LONGTEXT DEFAULT NULL,
            products_source VARCHAR(20) DEFAULT \'latest\' NOT NULL,
            products_limit INT DEFAULT 8 NOT NULL,
            articles_enabled TINYINT(1) DEFAULT 1 NOT NULL,
            articles_title VARCHAR(255) DEFAULT NULL,
            articles_lead LONGTEXT DEFAULT NULL,
            articles_source VARCHAR(20) DEFAULT \'latest\' NOT NULL,
            articles_limit INT DEFAULT 3 NOT NULL,
            site_id INT NOT NULL,
            locale_id INT NOT NULL,
            INDEX IDX_A738DB0FF6BD1646 (site_id),
            INDEX IDX_A738DB0FE559DFD1 (locale_id),
            UNIQUE INDEX uniq_shop_homepage_setting_site_locale (site_id, locale_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE shop_homepage_setting_product (
            homepage_setting_id INT NOT NULL,
            product_id INT NOT NULL,
            INDEX IDX_FDC86685B9D40968 (homepage_setting_id),
            INDEX IDX_FDC866854584665A (product_id),
            PRIMARY KEY(homepage_setting_id, product_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE shop_homepage_setting_article (
            homepage_setting_id INT NOT NULL,
            blog_article_id INT NOT NULL,
            INDEX IDX_2CB86C4EB9D40968 (homepage_setting_id),
            INDEX IDX_2CB86C4E9452A475 (blog_article_id),
            PRIMARY KEY(homepage_setting_id, blog_article_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE shop_homepage_setting ADD CONSTRAINT FK_A738DB0FF6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_homepage_setting ADD CONSTRAINT FK_A738DB0FE559DFD1 FOREIGN KEY (locale_id) REFERENCES locale (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_homepage_setting_product ADD CONSTRAINT FK_FDC86685B9D40968 FOREIGN KEY (homepage_setting_id) REFERENCES shop_homepage_setting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_homepage_setting_product ADD CONSTRAINT FK_FDC866854584665A FOREIGN KEY (product_id) REFERENCES shop_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_homepage_setting_article ADD CONSTRAINT FK_2CB86C4EB9D40968 FOREIGN KEY (homepage_setting_id) REFERENCES shop_homepage_setting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_homepage_setting_article ADD CONSTRAINT FK_2CB86C4E9452A475 FOREIGN KEY (blog_article_id) REFERENCES shop_blog_article (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shop_homepage_setting_article');
        $this->addSql('DROP TABLE shop_homepage_setting_product');
        $this->addSql('DROP TABLE shop_homepage_setting');
    }
}
