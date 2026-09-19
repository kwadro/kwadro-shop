<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909280000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change header_translation.work_hours to text for HTML content';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('header_translation') && $schema->getTable('header_translation')->hasColumn('work_hours')) {
            $this->addSql('ALTER TABLE header_translation CHANGE work_hours work_hours LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('header_translation') && $schema->getTable('header_translation')->hasColumn('work_hours')) {
            $this->addSql('ALTER TABLE header_translation CHANGE work_hours work_hours VARCHAR(255) DEFAULT NULL');
        }
    }
}
