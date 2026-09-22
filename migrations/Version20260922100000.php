<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop_category table and seed Default root category';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_category')) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_category (
                id INT AUTO_INCREMENT NOT NULL,
                parent_id INT DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                level INT DEFAULT 0 NOT NULL,
                position INT DEFAULT 0 NOT NULL,
                created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_shop_category_parent (parent_id),
                UNIQUE INDEX uniq_shop_category_slug (slug),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE shop_category ADD CONSTRAINT FK_shop_category_parent FOREIGN KEY (parent_id) REFERENCES shop_category (id) ON DELETE SET NULL');
    }

    public function postUp(Schema $schema): void
    {
        // Align collation if an older unicode_ci table already exists
        $this->connection->executeStatement(
            'ALTER TABLE shop_category CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'
        );
        $exists = $this->connection->fetchOne(
            'SELECT id FROM shop_category WHERE slug = ? OR name = ?',
            ['default', 'Default'],
        );

        if ($exists !== false) {
            return;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->insert('shop_category', [
            'parent_id' => null,
            'name' => 'Default',
            'slug' => 'default',
            'level' => 0,
            'position' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('shop_category')) {
            return;
        }

        $this->addSql('ALTER TABLE shop_category DROP FOREIGN KEY FK_shop_category_parent');
        $this->addSql('DROP TABLE shop_category');
    }
}
