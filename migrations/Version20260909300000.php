<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909300000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add courier delivery cost to site';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('site') && !$schema->getTable('site')->hasColumn('courier_delivery_cost')) {
            $this->addSql('ALTER TABLE site ADD courier_delivery_cost NUMERIC(12, 2) DEFAULT 100 NOT NULL');
        }
    }

    public function postUp(Schema $schema): void
    {
        if (!$schema->hasTable('site') || !$schema->getTable('site')->hasColumn('courier_delivery_cost')) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE site SET courier_delivery_cost = 100 WHERE courier_delivery_cost IS NULL OR courier_delivery_cost <= 0'
        );

        if (!$schema->hasTable('header_setting') || !$schema->getTable('header_setting')->hasColumn('courier_delivery_cost')) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE site s
             INNER JOIN header_setting hs ON hs.site_id = s.id
             SET s.courier_delivery_cost = hs.courier_delivery_cost
             WHERE hs.courier_delivery_cost IS NOT NULL AND hs.courier_delivery_cost > 0'
        );
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('site') && $schema->getTable('site')->hasColumn('courier_delivery_cost')) {
            $this->addSql('ALTER TABLE site DROP courier_delivery_cost');
        }
    }
}
