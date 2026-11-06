<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105235000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mailbox message link and conversation id';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_mailbox_message');
        if (!$table->hasColumn('link_type')) {
            $this->addSql('ALTER TABLE shop_mailbox_message ADD link_type VARCHAR(16) DEFAULT NULL');
        }
        if (!$table->hasColumn('link_ref')) {
            $this->addSql('ALTER TABLE shop_mailbox_message ADD link_ref VARCHAR(64) DEFAULT NULL');
        }
        if (!$table->hasColumn('conversation_id')) {
            $this->addSql('ALTER TABLE shop_mailbox_message ADD conversation_id VARCHAR(36) DEFAULT NULL');
            $this->addSql('CREATE INDEX idx_mailbox_message_conversation ON shop_mailbox_message (conversation_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_mailbox_message');
        if ($table->hasColumn('conversation_id')) {
            $this->addSql('DROP INDEX idx_mailbox_message_conversation ON shop_mailbox_message');
            $this->addSql('ALTER TABLE shop_mailbox_message DROP conversation_id');
        }
        if ($table->hasColumn('link_ref')) {
            $this->addSql('ALTER TABLE shop_mailbox_message DROP link_ref');
        }
        if ($table->hasColumn('link_type')) {
            $this->addSql('ALTER TABLE shop_mailbox_message DROP link_type');
        }
    }
}
