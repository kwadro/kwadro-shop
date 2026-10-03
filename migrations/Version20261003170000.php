<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message_group to mailbox messages';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_mailbox_message');
        if (!$table->hasColumn('message_group')) {
            $this->addSql("ALTER TABLE shop_mailbox_message ADD message_group VARCHAR(255) DEFAULT 'General' NOT NULL");
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_mailbox_message');
        if ($table->hasColumn('message_group')) {
            $this->addSql('ALTER TABLE shop_mailbox_message DROP message_group');
        }
    }
}
