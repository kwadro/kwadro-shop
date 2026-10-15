<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add site.log_admin_actions and shop_admin_audit_log journal';
    }

    public function up(Schema $schema): void
    {
        $site = $schema->getTable('site');
        if (!$site->hasColumn('log_admin_actions')) {
            $this->addSql('ALTER TABLE site ADD log_admin_actions TINYINT(1) DEFAULT 0 NOT NULL');
        }

        if (!$schema->hasTable('shop_admin_audit_log')) {
            $this->addSql('CREATE TABLE shop_admin_audit_log (
                id INT AUTO_INCREMENT NOT NULL,
                account_name VARCHAR(255) NOT NULL,
                action VARCHAR(32) NOT NULL,
                entity_class VARCHAR(255) DEFAULT NULL,
                entity_id VARCHAR(64) DEFAULT NULL,
                entity_label VARCHAR(512) DEFAULT NULL,
                changes JSON DEFAULT NULL,
                ip VARCHAR(45) DEFAULT NULL,
                request_path VARCHAR(1024) DEFAULT NULL,
                created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX idx_admin_audit_account (account_name),
                INDEX idx_admin_audit_action (action),
                INDEX idx_admin_audit_created (created_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_admin_audit_log')) {
            $this->addSql('DROP TABLE shop_admin_audit_log');
        }

        $site = $schema->getTable('site');
        if ($site->hasColumn('log_admin_actions')) {
            $this->addSql('ALTER TABLE site DROP log_admin_actions');
        }
    }
}
