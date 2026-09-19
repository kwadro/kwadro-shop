<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add order number sequence for KV-000001 format';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_order_number_sequence')) {
            $this->addSql('CREATE TABLE shop_order_number_sequence (id INT NOT NULL, last_number INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('INSERT INTO shop_order_number_sequence (id, last_number) VALUES (1, 0)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_order_number_sequence')) {
            $this->addSql('DROP TABLE shop_order_number_sequence');
        }
    }

    public function postUp(Schema $schema): void
    {
        if (!$schema->hasTable('shop_order_number_sequence')) {
            return;
        }

        $maxNumber = $this->findMaxKvOrderNumber();

        $this->connection->executeStatement(
            'UPDATE shop_order_number_sequence SET last_number = :lastNumber WHERE id = 1',
            ['lastNumber' => $maxNumber],
        );
    }

    private function findMaxKvOrderNumber(): int
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['shop_order'])) {
            return 0;
        }

        $rows = $this->connection->fetchFirstColumn(
            "SELECT order_number FROM shop_order WHERE order_number LIKE 'KV-%'"
        );

        $maxNumber = 0;
        foreach ($rows as $orderNumber) {
            if (!\is_string($orderNumber) || !preg_match('/^KV-(\d+)$/', $orderNumber, $matches)) {
                continue;
            }

            $maxNumber = max($maxNumber, (int) $matches[1]);
        }

        return $maxNumber;
    }
}
