<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add phones field to header_setting';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('header_setting') && !$schema->getTable('header_setting')->hasColumn('phones')) {
            $this->addSql('ALTER TABLE header_setting ADD phones LONGTEXT DEFAULT NULL');
        }
    }

    public function postUp(Schema $schema): void
    {
        if (!$schema->hasTable('header_setting') || !$schema->getTable('header_setting')->hasColumn('phones')) {
            return;
        }

        $this->connection->executeStatement(
            "UPDATE header_setting SET phones = '066-913-30-97 · 067-343-70-40' WHERE phones IS NULL OR phones = ''"
        );
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('header_setting') && $schema->getTable('header_setting')->hasColumn('phones')) {
            $this->addSql('ALTER TABLE header_setting DROP phones');
        }
    }
}
