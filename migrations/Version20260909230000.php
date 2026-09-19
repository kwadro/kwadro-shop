<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add visitor id sequence table for numeric visitor identifiers';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_visitor_id_sequence')) {
            $this->addSql('CREATE TABLE shop_visitor_id_sequence (id INT NOT NULL, last_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('INSERT INTO shop_visitor_id_sequence (id, last_id) VALUES (1, 999)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_visitor_id_sequence')) {
            $this->addSql('DROP TABLE shop_visitor_id_sequence');
        }
    }

    public function postUp(Schema $schema): void
    {
        if (!$schema->hasTable('shop_visitor_id_sequence')) {
            return;
        }

        $maxNumericId = max(
            $this->findMaxNumericVisitorId('shop_cart'),
            $this->findMaxNumericVisitorId('shop_order'),
            999,
        );

        $this->connection->executeStatement(
            'UPDATE shop_visitor_id_sequence SET last_id = :lastId WHERE id = 1',
            ['lastId' => $maxNumericId],
        );
    }

    private function findMaxNumericVisitorId(string $table): int
    {
        if (!$this->connection->createSchemaManager()->tablesExist([$table])) {
            return 999;
        }

        $max = $this->connection->fetchOne(
            sprintf('SELECT MAX(CAST(visitor_id AS UNSIGNED)) FROM %s WHERE visitor_id REGEXP \'^[0-9]+$\'' , $table)
        );

        return is_numeric($max) ? (int) $max : 999;
    }
}
