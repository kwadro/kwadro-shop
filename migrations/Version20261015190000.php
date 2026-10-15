<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Admin toast notifications for cart / order / payment changes';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_admin_shop_notification')) {
            return;
        }

        $this->addSql('CREATE TABLE shop_admin_shop_notification (
            id INT AUTO_INCREMENT NOT NULL,
            channel VARCHAR(32) NOT NULL,
            action VARCHAR(32) NOT NULL,
            entity_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body LONGTEXT NOT NULL,
            target_path VARCHAR(512) DEFAULT NULL,
            is_notified TINYINT(1) DEFAULT 0 NOT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_admin_shop_notify_pending (is_notified, created_at),
            INDEX idx_admin_shop_notify_entity (channel, entity_id, is_notified),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_admin_shop_notification')) {
            $this->addSql('DROP TABLE shop_admin_shop_notification');
        }
    }
}
