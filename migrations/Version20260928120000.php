<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add site admin_email and order email send_copy_to_admin flag';
    }

    public function up(Schema $schema): void
    {
        $site = $schema->getTable('site');
        if (!$site->hasColumn('admin_email')) {
            $this->addSql('ALTER TABLE site ADD admin_email VARCHAR(255) DEFAULT NULL');
        }

        $orderEmail = $schema->getTable('shop_order_email');
        if (!$orderEmail->hasColumn('send_copy_to_admin')) {
            $this->addSql('ALTER TABLE shop_order_email ADD send_copy_to_admin TINYINT(1) DEFAULT 0 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $orderEmail = $schema->getTable('shop_order_email');
        if ($orderEmail->hasColumn('send_copy_to_admin')) {
            $this->addSql('ALTER TABLE shop_order_email DROP send_copy_to_admin');
        }

        $site = $schema->getTable('site');
        if ($site->hasColumn('admin_email')) {
            $this->addSql('ALTER TABLE site DROP admin_email');
        }
    }
}
