<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add title to seo_settings_translation';
    }

    public function up(Schema $schema): void
    {
        $tableName = 'seo_settings_translation';
        if (!$schema->hasTable($tableName)) {
            return;
        }

        $table = $schema->getTable($tableName);
        if (!$table->hasColumn('title')) {
            $this->addSql('ALTER TABLE seo_settings_translation ADD title VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $tableName = 'seo_settings_translation';
        if (!$schema->hasTable($tableName)) {
            return;
        }

        $table = $schema->getTable($tableName);
        if ($table->hasColumn('title')) {
            $this->addSql('ALTER TABLE seo_settings_translation DROP title');
        }
    }
}
