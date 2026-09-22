<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add enabled and SEO fields to shop_category';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_category');

        if (!$table->hasColumn('enabled')) {
            $this->addSql('ALTER TABLE shop_category ADD enabled TINYINT(1) DEFAULT 1 NOT NULL');
        }
        if (!$table->hasColumn('meta_title')) {
            $this->addSql('ALTER TABLE shop_category ADD meta_title VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('meta_description')) {
            $this->addSql('ALTER TABLE shop_category ADD meta_description LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_category');

        if ($table->hasColumn('meta_description')) {
            $this->addSql('ALTER TABLE shop_category DROP meta_description');
        }
        if ($table->hasColumn('meta_title')) {
            $this->addSql('ALTER TABLE shop_category DROP meta_title');
        }
        if ($table->hasColumn('enabled')) {
            $this->addSql('ALTER TABLE shop_category DROP enabled');
        }
    }
}
