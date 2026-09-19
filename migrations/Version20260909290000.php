<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909290000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add courier delivery cost to header_setting';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('header_setting') && !$schema->getTable('header_setting')->hasColumn('courier_delivery_cost')) {
            $this->addSql('ALTER TABLE header_setting ADD courier_delivery_cost NUMERIC(12, 2) DEFAULT 100 NOT NULL');
        }
    }

    public function postUp(Schema $schema): void
    {
        if (!$schema->hasTable('header_setting') || !$schema->getTable('header_setting')->hasColumn('courier_delivery_cost')) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE header_setting SET courier_delivery_cost = 100 WHERE courier_delivery_cost IS NULL OR courier_delivery_cost <= 0'
        );
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('header_setting') && $schema->getTable('header_setting')->hasColumn('courier_delivery_cost')) {
            $this->addSql('ALTER TABLE header_setting DROP courier_delivery_cost');
        }
    }
}
