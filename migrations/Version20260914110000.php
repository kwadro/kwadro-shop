<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop email log table for sent email correspondence';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_email_log')) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_email_log (
                id INT AUTO_INCREMENT NOT NULL,
                order_id INT DEFAULT NULL,
                order_email_id INT DEFAULT NULL,
                event_code VARCHAR(64) NOT NULL,
                order_number VARCHAR(64) DEFAULT NULL,
                recipient_email VARCHAR(255) NOT NULL,
                sender_email VARCHAR(255) DEFAULT NULL,
                sender_name VARCHAR(255) DEFAULT NULL,
                subject VARCHAR(255) DEFAULT NULL,
                body LONGTEXT DEFAULT NULL,
                is_html TINYINT(1) DEFAULT 0 NOT NULL,
                status VARCHAR(16) NOT NULL,
                error_message LONGTEXT DEFAULT NULL,
                skip_reason LONGTEXT DEFAULT NULL,
                context JSON DEFAULT NULL,
                created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_EMAIL_LOG_ORDER (order_id),
                INDEX IDX_EMAIL_LOG_ORDER_EMAIL (order_email_id),
                INDEX IDX_EMAIL_LOG_STATUS (status),
                INDEX IDX_EMAIL_LOG_CREATED_AT (created_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql('ALTER TABLE shop_email_log ADD CONSTRAINT FK_EMAIL_LOG_ORDER FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE shop_email_log ADD CONSTRAINT FK_EMAIL_LOG_ORDER_EMAIL FOREIGN KEY (order_email_id) REFERENCES shop_order_email (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_log')) {
            return;
        }

        $this->addSql('ALTER TABLE shop_email_log DROP FOREIGN KEY FK_EMAIL_LOG_ORDER');
        $this->addSql('ALTER TABLE shop_email_log DROP FOREIGN KEY FK_EMAIL_LOG_ORDER_EMAIL');
        $this->addSql('DROP TABLE shop_email_log');
    }
}
