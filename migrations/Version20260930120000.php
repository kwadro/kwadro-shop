<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop_request_list for visitor IP/path logging';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_request_list')) {
            return;
        }

        $this->addSql('CREATE TABLE shop_request_list (
            id INT AUTO_INCREMENT NOT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            path VARCHAR(2048) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_request_list_created (created_at),
            INDEX idx_request_list_ip_created (ip, created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_request_list')) {
            $this->addSql('DROP TABLE shop_request_list');
        }
    }
}
