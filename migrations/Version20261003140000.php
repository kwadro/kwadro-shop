<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add allowed_from_emails filter to mailbox accounts';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_mailbox_account');
        if (!$table->hasColumn('allowed_from_emails')) {
            $this->addSql('ALTER TABLE shop_mailbox_account ADD allowed_from_emails LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_mailbox_account');
        if ($table->hasColumn('allowed_from_emails')) {
            $this->addSql('ALTER TABLE shop_mailbox_account DROP allowed_from_emails');
        }
    }
}
