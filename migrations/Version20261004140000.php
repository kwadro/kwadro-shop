<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SEO/OG fields to shop_blog_article';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_blog_article');
        if (!$table->hasColumn('og_title')) {
            $this->addSql('ALTER TABLE shop_blog_article ADD og_title VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('og_description')) {
            $this->addSql('ALTER TABLE shop_blog_article ADD og_description LONGTEXT DEFAULT NULL');
        }
        if (!$table->hasColumn('og_type')) {
            $this->addSql('ALTER TABLE shop_blog_article ADD og_type VARCHAR(64) DEFAULT NULL');
        }
        if (!$table->hasColumn('og_image')) {
            $this->addSql('ALTER TABLE shop_blog_article ADD og_image VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_blog_article');
        foreach (['og_title', 'og_description', 'og_type', 'og_image'] as $column) {
            if ($table->hasColumn($column)) {
                $this->addSql(sprintf('ALTER TABLE shop_blog_article DROP %s', $column));
            }
        }
    }
}
