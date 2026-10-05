<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add blog article tags and blog category SEO/OG fields';
    }

    public function up(Schema $schema): void
    {
        $article = $schema->getTable('shop_blog_article');
        if (!$article->hasColumn('tags')) {
            $this->addSql('ALTER TABLE shop_blog_article ADD tags LONGTEXT DEFAULT NULL');
        }

        $category = $schema->getTable('shop_blog_category');
        if (!$category->hasColumn('meta_title')) {
            $this->addSql('ALTER TABLE shop_blog_category ADD meta_title VARCHAR(255) DEFAULT NULL');
        }
        if (!$category->hasColumn('meta_description')) {
            $this->addSql('ALTER TABLE shop_blog_category ADD meta_description LONGTEXT DEFAULT NULL');
        }
        if (!$category->hasColumn('og_title')) {
            $this->addSql('ALTER TABLE shop_blog_category ADD og_title VARCHAR(255) DEFAULT NULL');
        }
        if (!$category->hasColumn('og_description')) {
            $this->addSql('ALTER TABLE shop_blog_category ADD og_description LONGTEXT DEFAULT NULL');
        }
        if (!$category->hasColumn('og_type')) {
            $this->addSql('ALTER TABLE shop_blog_category ADD og_type VARCHAR(64) DEFAULT NULL');
        }
        if (!$category->hasColumn('og_image')) {
            $this->addSql('ALTER TABLE shop_blog_category ADD og_image VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $article = $schema->getTable('shop_blog_article');
        if ($article->hasColumn('tags')) {
            $this->addSql('ALTER TABLE shop_blog_article DROP tags');
        }

        $category = $schema->getTable('shop_blog_category');
        foreach (['meta_title', 'meta_description', 'og_title', 'og_description', 'og_type', 'og_image'] as $column) {
            if ($category->hasColumn($column)) {
                $this->addSql(sprintf('ALTER TABLE shop_blog_category DROP %s', $column));
            }
        }
    }
}
