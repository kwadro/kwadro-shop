<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store last synced IMAP UID on mailbox accounts';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_mailbox_account');
        if (!$table->hasColumn('last_synced_remote_uid')) {
            $this->addSql('ALTER TABLE shop_mailbox_account ADD last_synced_remote_uid VARCHAR(64) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_mailbox_account');
        if ($table->hasColumn('last_synced_remote_uid')) {
            $this->addSql('ALTER TABLE shop_mailbox_account DROP last_synced_remote_uid');
        }
    }
}
