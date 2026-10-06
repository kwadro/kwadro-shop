<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product import config + run history; seed SiViTek import';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_product_import')) {
            $this->addSql('CREATE TABLE shop_product_import (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(120) NOT NULL,
                code VARCHAR(64) NOT NULL,
                mode VARCHAR(32) DEFAULT \'add_update\' NOT NULL,
                file_path VARCHAR(512) NOT NULL,
                disabled_skus JSON NOT NULL,
                active TINYINT(1) DEFAULT 1 NOT NULL,
                last_run_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                last_result LONGTEXT DEFAULT NULL,
                created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX uniq_shop_product_import_code (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$schema->hasTable('shop_product_import_run')) {
            $this->addSql('CREATE TABLE shop_product_import_run (
                id INT AUTO_INCREMENT NOT NULL,
                product_import_id INT NOT NULL,
                started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                finished_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                status VARCHAR(16) NOT NULL,
                created_count INT DEFAULT 0 NOT NULL,
                updated_count INT DEFAULT 0 NOT NULL,
                disabled_count INT DEFAULT 0 NOT NULL,
                deleted_count INT DEFAULT 0 NOT NULL,
                result LONGTEXT DEFAULT NULL,
                error_message LONGTEXT DEFAULT NULL,
                INDEX IDX_PRODUCT_IMPORT_RUN_IMPORT (product_import_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_PRODUCT_IMPORT_RUN_IMPORT FOREIGN KEY (product_import_id) REFERENCES shop_product_import (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        $this->addSql(
            "INSERT INTO shop_product_import (name, code, mode, file_path, disabled_skus, active, created_at, updated_at)
             SELECT 'Import SiViTek', 'sivitek', 'add_update', 'data-price/прайс опт SiViTek.xls', JSON_ARRAY(), 1, NOW(), NOW()
             FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM shop_product_import WHERE code = 'sivitek')"
        );
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_product_import_run')) {
            $this->addSql('DROP TABLE shop_product_import_run');
        }
        if ($schema->hasTable('shop_product_import')) {
            $this->addSql('DROP TABLE shop_product_import');
        }
    }
}
