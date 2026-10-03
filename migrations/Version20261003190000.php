<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add OG fields to shop_category';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_category');
        if (!$table->hasColumn('og_title')) {
            $this->addSql('ALTER TABLE shop_category ADD og_title VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('og_description')) {
            $this->addSql('ALTER TABLE shop_category ADD og_description LONGTEXT DEFAULT NULL');
        }
        if (!$table->hasColumn('og_type')) {
            $this->addSql('ALTER TABLE shop_category ADD og_type VARCHAR(64) DEFAULT NULL');
        }
        if (!$table->hasColumn('og_image')) {
            $this->addSql('ALTER TABLE shop_category ADD og_image VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_category');
        foreach (['og_title', 'og_description', 'og_type', 'og_image'] as $column) {
            if ($table->hasColumn($column)) {
                $this->addSql(sprintf('ALTER TABLE shop_category DROP %s', $column));
            }
        }
    }
}
