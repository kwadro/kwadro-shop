<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace product.category string with ManyToMany shop_product_category';
    }

    public function up(Schema $schema): void
    {
        // Ensure category tables match project collation (utf8mb4_general_ci)
        if ($schema->hasTable('shop_category')) {
            $this->addSql('ALTER TABLE shop_category CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        }

        if (!$schema->hasTable('shop_product_category')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE shop_product_category (
                    product_id INT NOT NULL,
                    category_id INT NOT NULL,
                    INDEX IDX_shop_product_category_product (product_id),
                    INDEX IDX_shop_product_category_category (category_id),
                    PRIMARY KEY(product_id, category_id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB
            SQL);
            $this->addSql('ALTER TABLE shop_product_category ADD CONSTRAINT FK_shop_product_category_product FOREIGN KEY (product_id) REFERENCES shop_product (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE shop_product_category ADD CONSTRAINT FK_shop_product_category_category FOREIGN KEY (category_id) REFERENCES shop_category (id) ON DELETE CASCADE');
        } else {
            $this->addSql('ALTER TABLE shop_product_category CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        }

        if ($schema->hasTable('shop_product') && $schema->getTable('shop_product')->hasColumn('category')) {
            $this->addSql(<<<'SQL'
                INSERT IGNORE INTO shop_product_category (product_id, category_id)
                SELECT p.id, c.id
                FROM shop_product p
                INNER JOIN shop_category c
                    ON c.name COLLATE utf8mb4_general_ci = p.category COLLATE utf8mb4_general_ci
                WHERE p.category IS NOT NULL AND p.category <> ''
            SQL);
            $this->addSql('ALTER TABLE shop_product DROP category');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_product') && !$schema->getTable('shop_product')->hasColumn('category')) {
            $this->addSql("ALTER TABLE shop_product ADD category VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT ''");
            $this->addSql(<<<'SQL'
                UPDATE shop_product p
                SET category = COALESCE((
                    SELECT c.name
                    FROM shop_product_category pc
                    INNER JOIN shop_category c ON c.id = pc.category_id
                    WHERE pc.product_id = p.id
                    ORDER BY c.level ASC, c.position ASC, c.name ASC
                    LIMIT 1
                ), '')
            SQL);
        }

        if ($schema->hasTable('shop_product_category')) {
            $this->addSql('ALTER TABLE shop_product_category DROP FOREIGN KEY FK_shop_product_category_product');
            $this->addSql('ALTER TABLE shop_product_category DROP FOREIGN KEY FK_shop_product_category_category');
            $this->addSql('DROP TABLE shop_product_category');
        }
    }
}
