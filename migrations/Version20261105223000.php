<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parent_id to mailbox messages for sent and received threads';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_mailbox_message');
        if ($table->hasColumn('parent_id')) {
            return;
        }

        $this->addSql('ALTER TABLE shop_mailbox_message ADD parent_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_mailbox_message_parent ON shop_mailbox_message (parent_id)');
        $this->addSql('ALTER TABLE shop_mailbox_message ADD CONSTRAINT FK_mailbox_message_parent FOREIGN KEY (parent_id) REFERENCES shop_mailbox_message (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_mailbox_message');
        if (!$table->hasColumn('parent_id')) {
            return;
        }

        $this->addSql('ALTER TABLE shop_mailbox_message DROP FOREIGN KEY FK_mailbox_message_parent');
        $this->addSql('DROP INDEX idx_mailbox_message_parent ON shop_mailbox_message');
        $this->addSql('ALTER TABLE shop_mailbox_message DROP parent_id');
    }
}
