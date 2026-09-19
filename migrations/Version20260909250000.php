<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909250000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer contact fields to app_user';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('app_user') && !$schema->getTable('app_user')->hasColumn('full_name')) {
            $this->addSql('ALTER TABLE app_user ADD full_name VARCHAR(255) DEFAULT NULL, ADD phone VARCHAR(32) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('app_user') && $schema->getTable('app_user')->hasColumn('full_name')) {
            $this->addSql('ALTER TABLE app_user DROP full_name, DROP phone');
        }
    }
}
