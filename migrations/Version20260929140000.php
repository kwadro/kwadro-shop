<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create blog categories and articles tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_blog_category (
            id INT AUTO_INCREMENT NOT NULL,
            site_id INT NOT NULL,
            locale_id INT NOT NULL,
            parent_id INT DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            enabled TINYINT(1) DEFAULT 1 NOT NULL,
            level INT DEFAULT 0 NOT NULL,
            position INT DEFAULT 0 NOT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_blog_category_site (site_id),
            INDEX IDX_blog_category_locale (locale_id),
            INDEX IDX_blog_category_parent (parent_id),
            UNIQUE INDEX uniq_blog_category_site_locale_slug (site_id, locale_id, slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE shop_blog_article (
            id INT AUTO_INCREMENT NOT NULL,
            site_id INT NOT NULL,
            locale_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            meta_title VARCHAR(255) DEFAULT NULL,
            meta_description LONGTEXT DEFAULT NULL,
            slug VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            enabled TINYINT(1) DEFAULT 1 NOT NULL,
            published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_blog_article_site (site_id),
            INDEX IDX_blog_article_locale (locale_id),
            INDEX idx_blog_article_published (enabled, published_at),
            UNIQUE INDEX uniq_blog_article_site_locale_slug (site_id, locale_id, slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE shop_blog_article_category (
            blog_article_id INT NOT NULL,
            blog_category_id INT NOT NULL,
            INDEX IDX_blog_article_cat_article (blog_article_id),
            INDEX IDX_blog_article_cat_category (blog_category_id),
            PRIMARY KEY(blog_article_id, blog_category_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE shop_blog_category ADD CONSTRAINT FK_blog_category_site FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_blog_category ADD CONSTRAINT FK_blog_category_locale FOREIGN KEY (locale_id) REFERENCES locale (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_blog_category ADD CONSTRAINT FK_blog_category_parent FOREIGN KEY (parent_id) REFERENCES shop_blog_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE shop_blog_article ADD CONSTRAINT FK_blog_article_site FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_blog_article ADD CONSTRAINT FK_blog_article_locale FOREIGN KEY (locale_id) REFERENCES locale (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_blog_article_category ADD CONSTRAINT FK_blog_ac_article FOREIGN KEY (blog_article_id) REFERENCES shop_blog_article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_blog_article_category ADD CONSTRAINT FK_blog_ac_category FOREIGN KEY (blog_category_id) REFERENCES shop_blog_category (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_blog_article_category DROP FOREIGN KEY FK_blog_ac_article');
        $this->addSql('ALTER TABLE shop_blog_article_category DROP FOREIGN KEY FK_blog_ac_category');
        $this->addSql('ALTER TABLE shop_blog_article DROP FOREIGN KEY FK_blog_article_site');
        $this->addSql('ALTER TABLE shop_blog_article DROP FOREIGN KEY FK_blog_article_locale');
        $this->addSql('ALTER TABLE shop_blog_category DROP FOREIGN KEY FK_blog_category_site');
        $this->addSql('ALTER TABLE shop_blog_category DROP FOREIGN KEY FK_blog_category_locale');
        $this->addSql('ALTER TABLE shop_blog_category DROP FOREIGN KEY FK_blog_category_parent');
        $this->addSql('DROP TABLE shop_blog_article_category');
        $this->addSql('DROP TABLE shop_blog_article');
        $this->addSql('DROP TABLE shop_blog_category');
    }
}
