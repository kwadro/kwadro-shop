<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ensures admin_email / send_copy_to_admin exist even if Version20260928120000
 * was marked executed while skipping ALTER (ORM schema hasColumn false-positive).
 */
final class Version20260928150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure site.admin_email and shop_order_email.send_copy_to_admin columns exist';
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('site', 'admin_email')) {
            $this->addSql('ALTER TABLE site ADD admin_email VARCHAR(255) DEFAULT NULL');
        }

        if (!$this->columnExists('shop_order_email', 'send_copy_to_admin')) {
            $this->addSql('ALTER TABLE shop_order_email ADD send_copy_to_admin TINYINT(1) DEFAULT 0 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // Keep columns — original migration owns rollback.
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            [$table, $column],
        );

        return (int) $result > 0;
    }
}
