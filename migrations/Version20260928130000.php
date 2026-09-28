<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mailbox accounts and synced mailbox messages for admin inbox';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_mailbox_account (
            id INT AUTO_INCREMENT NOT NULL,
            site_id INT DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            username VARCHAR(255) NOT NULL,
            password_encrypted LONGTEXT NOT NULL,
            imap_host VARCHAR(255) NOT NULL,
            imap_port INT NOT NULL,
            imap_encryption VARCHAR(16) NOT NULL,
            smtp_host VARCHAR(255) NOT NULL,
            smtp_port INT NOT NULL,
            smtp_encryption VARCHAR(16) NOT NULL,
            is_active TINYINT(1) DEFAULT 1 NOT NULL,
            last_synced_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            last_sync_error LONGTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_mailbox_account_site (site_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE shop_mailbox_message (
            id INT AUTO_INCREMENT NOT NULL,
            mailbox_id INT NOT NULL,
            remote_uid VARCHAR(64) NOT NULL,
            message_id VARCHAR(512) DEFAULT NULL,
            from_address VARCHAR(255) NOT NULL,
            from_name VARCHAR(255) DEFAULT NULL,
            to_addresses LONGTEXT DEFAULT NULL,
            subject VARCHAR(500) NOT NULL,
            body_preview LONGTEXT DEFAULT NULL,
            body_text LONGTEXT DEFAULT NULL,
            body_html LONGTEXT DEFAULT NULL,
            received_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            is_seen TINYINT(1) DEFAULT 0 NOT NULL,
            is_notified TINYINT(1) DEFAULT 0 NOT NULL,
            has_attachments TINYINT(1) DEFAULT 0 NOT NULL,
            folder VARCHAR(64) DEFAULT \'INBOX\' NOT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_mailbox_message_mailbox (mailbox_id),
            INDEX idx_mailbox_message_received (received_at),
            INDEX idx_mailbox_message_notified (is_notified, is_seen),
            UNIQUE INDEX uniq_mailbox_remote_uid (mailbox_id, remote_uid),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE shop_mailbox_account ADD CONSTRAINT FK_mailbox_account_site FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE shop_mailbox_message ADD CONSTRAINT FK_mailbox_message_mailbox FOREIGN KEY (mailbox_id) REFERENCES shop_mailbox_account (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_mailbox_message DROP FOREIGN KEY FK_mailbox_message_mailbox');
        $this->addSql('ALTER TABLE shop_mailbox_account DROP FOREIGN KEY FK_mailbox_account_site');
        $this->addSql('DROP TABLE shop_mailbox_message');
        $this->addSql('DROP TABLE shop_mailbox_account');
    }
}
