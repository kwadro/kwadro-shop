<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store mailbox message attachment metadata for download links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE shop_mailbox_message ADD attachments JSON DEFAULT ('[]') NOT NULL COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_mailbox_message DROP attachments');
    }
}
